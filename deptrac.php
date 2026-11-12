<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\ClassLikeConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

// Module boundaries: docs/development/architecture.md.
// ClassLikeConfig::create() escapes backslashes itself: '\\' below is one namespace separator.
return static function (DeptracConfig $config): void {
    $layer = static fn (string $name, string $regex): Layer => Layer::withName($name)->collectors(ClassLikeConfig::create($regex));

    $vendor       = $layer('Vendor', '^(?!Packio\\|Common\\).+');
    $common       = $layer('Common', '^Common\\');
    $sharedApi    = $layer('SharedApi', '^Packio\\Shared\\Api\\');
    $sharedDomain = $layer('SharedDomain', '^Packio\\Shared\\Domain\\');
    $sharedInfra  = $layer('SharedInfra', '^Packio\\Shared\\(?!Api\\|Domain\\)');
    $platform     = $layer('Platform', '^Packio\\Application\\');
    $base         = [$vendor, $common, $sharedApi, $sharedDomain];

    // Every directory in src/Module is a module, so a new module is checked without editing this file.
    $modules = [];
    foreach (glob(__DIR__ . '/src/Module/*', GLOB_ONLYDIR) as $dir) {
        $m = basename($dir);
        if ('Shared' !== $m) {
            $modules[$m] = [
                'api'         => $layer("{$m}Api", "^Packio\\{$m}\\Api\\"),
                'domain'      => $layer("{$m}Domain", "^Packio\\{$m}\\Domain\\"),
                'application' => $layer("{$m}Application", "^Packio\\{$m}\\Application\\"),
                'adapter'     => $layer("{$m}Adapter", "^Packio\\{$m}\\Infrastructure\\Adapter\\"),
                'facade'      => $layer("{$m}Facade", "^Packio\\{$m}\\Infrastructure\\Facade\\"),
                // Entry: handlers, pages, middleware, CLI, resources, ConfigProvider.
                'entry'       => $layer("{$m}Entry", "^Packio\\{$m}\\(?!Api\\|Domain\\|Application\\|Infrastructure\\(Adapter|Facade)\\)"),
            ];
        }
    }
    $allApi = array_column($modules, 'api');

    $layers   = [...$base, $sharedInfra, $platform];
    $rulesets = [
        Ruleset::forLayer($vendor),
        Ruleset::forLayer($common)->accesses($vendor),
        Ruleset::forLayer($sharedDomain)->accesses($vendor, $common),
        Ruleset::forLayer($sharedApi)->accesses($vendor, $common, $sharedDomain),
        Ruleset::forLayer($sharedInfra)->accesses(...$base),
        // Platform is the HTTP composition root: it may use every module Api.
        Ruleset::forLayer($platform)->accesses(...[...$base, $sharedInfra, ...$allApi]),
    ];
    foreach ($modules as $p) {
        $own      = [$p['api'], $p['domain'], $p['application']];
        $layers   = [...$layers, ...array_values($p)];
        $rulesets = [
            ...$rulesets,
            Ruleset::forLayer($p['api'])->accesses(...$base),
            Ruleset::forLayer($p['domain'])->accesses(...[...$base, $p['api']]),
            Ruleset::forLayer($p['application'])->accesses(...[...$base, $p['api'], $p['domain']]),
            // Adapters are the only Core-side code that calls a foreign Api.
            Ruleset::forLayer($p['adapter'])->accesses(...[...$base, ...$own, $p['entry'], $sharedInfra, $platform, ...$allApi]),
            Ruleset::forLayer($p['facade'])->accesses(...[...$base, ...$own, $p['entry'], $p['adapter'], $sharedInfra, $platform]),
            Ruleset::forLayer($p['entry'])->accesses(...[...$base, ...$own, $p['adapter'], $p['facade'], $sharedInfra, $platform, ...$allApi]),
        ];
    }

    $config
        ->paths(__DIR__ . '/src')
        // DI configs are the composition root (convention C1).
        ->excludeFiles('#/config/#')
        ->cacheFile(__DIR__ . '/data/cache/.deptrac.cache')
        ->layers(...$layers)
        ->rulesets(...$rulesets);
};
