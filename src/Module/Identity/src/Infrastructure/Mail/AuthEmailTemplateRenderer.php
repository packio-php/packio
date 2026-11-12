<?php

declare(strict_types=1);

namespace Packio\Identity\Infrastructure\Mail;

use Common\Branding\BrandingConfig;
use Packio\Identity\Application\Mail\AuthEmailTemplateRendererInterface;
use Packio\Identity\Application\Mail\RenderedEmail;

use function array_map;
use function htmlspecialchars;
use function implode;
use function rtrim;
use function sprintf;

final readonly class AuthEmailTemplateRenderer implements AuthEmailTemplateRendererInterface
{
    public function __construct(private BrandingConfig $brandingConfig) {}

    public function renderEmailVerificationCode(string $code, int $ttlMinutes = 10): RenderedEmail
    {
        $productName     = $this->brandingConfig->productName();
        $safeProductName = $this->escape($productName);
        $tagline         = $this->escape($this->brandingConfig->tagline());
        $safeCode        = $this->escape($code);
        $safeTtlMinutes  = $this->escape((string) $ttlMinutes);

        $subject   = 'Your verification code';
        $preheader = sprintf(
            'Your %s verification code is %s. It expires in %s minutes.',
            $safeProductName,
            $safeCode,
            $safeTtlMinutes,
        );

        $metaParagraphs = [
            sprintf(
                'This code expires in <strong>%s minutes</strong>.',
                $safeTtlMinutes,
            ),
            'If you didn\'t request this code, you can safely ignore this email.',
        ];

        return new RenderedEmail(
            subject: $subject,
            html: $this->renderLayout(
                title: 'Verify your email',
                preheader: $preheader,
                description: sprintf(
                    'Use the verification code below to complete sign in and finish setting up your %s account.',
                    $safeProductName,
                ),
                productName: $safeProductName,
                tagline: $tagline,
                actionContent: $this->renderVerificationAction($safeCode),
                metaParagraphs: $metaParagraphs,
                footerText: sprintf(
                    '&copy; %s &middot; Secure access to your private Composer packages.',
                    $safeProductName,
                ),
            ),
        );
    }

    public function renderPasswordResetLink(string $resetUrl, int $ttlMinutes): RenderedEmail
    {
        $productName     = $this->brandingConfig->productName();
        $safeProductName = $this->escape($productName);
        $tagline         = $this->escape($this->brandingConfig->tagline());
        $safeResetUrl    = $this->escape($resetUrl);
        $safeTtlMinutes  = $this->escape((string) $ttlMinutes);

        $subject   = sprintf('Reset your %s password', $productName);
        $preheader = sprintf(
            'Reset your %s password. This secure link expires in %s minutes.',
            $safeProductName,
            $safeTtlMinutes,
        );

        $metaParagraphs = [
            sprintf(
                'This link expires in <strong>%s minutes</strong>.',
                $safeTtlMinutes,
            ),
            'If you didn\'t request a password reset, you can safely ignore this email and your current password will remain unchanged.',
        ];

        return new RenderedEmail(
            subject: $subject,
            html: $this->renderLayout(
                title: 'Reset your password',
                preheader: $preheader,
                description: sprintf(
                    'We received a request to reset the password for your %s account. Use the secure link below to choose a new password.',
                    $safeProductName,
                ),
                productName: $safeProductName,
                tagline: $tagline,
                actionContent: $this->renderPasswordResetAction($safeResetUrl),
                metaParagraphs: $metaParagraphs,
                footerText: sprintf(
                    '&copy; %s &middot; Secure access to your private Composer packages.',
                    $safeProductName,
                ),
            ),
        );
    }

    /**
     * @param list<string> $metaParagraphs
     */
    private function renderLayout(
        string $title,
        string $preheader,
        string $description,
        string $productName,
        string $tagline,
        string $actionContent,
        array $metaParagraphs,
        string $footerText,
    ): string {
        $metaHtml = implode(
            "\n",
            array_map(
                static fn (string $paragraph): string => sprintf(
                    '<tr><td align="center" style="padding: 0 0 16px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 24px; color: #6b7280;">%s</td></tr>',
                    $paragraph,
                ),
                $metaParagraphs,
            ),
        );
        $logoUrl = $this->escape($this->logoUrl());

        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>{$title}</title>
            </head>
            <body style="margin: 0; padding: 0; background-color: #f3f4f6; color: #111827;">
                <div style="display: none; font-size: 1px; color: #f3f4f6; line-height: 1px; max-height: 0; max-width: 0; opacity: 0; overflow: hidden;">
                    {$preheader}
                </div>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse; background-color: #f3f4f6;">
                    <tr>
                        <td align="center" style="padding: 24px 12px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; max-width: 640px; border-collapse: collapse;">
                                <tr>
                                    <td style="border-radius: 24px; background-color: #ffffff;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse;">
                                            <tr>
                                                <td align="center" bgcolor="#5856d6" style="padding: 32px 24px 28px; border-radius: 24px 24px 0 0; background-color: #5856d6;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse;">
                                                        <tr>
                                                            <td align="center" valign="middle" width="56" height="56" style="width: 56px; height: 56px; border-radius: 18px; background-color: #ffffff; font-family: Arial, Helvetica, sans-serif; font-size: 24px; line-height: 56px; font-weight: 700; color: #5856d6;">
                                                                <img src="{$logoUrl}" alt="{$productName}" width="56" height="56" style="display: block; width: 56px; height: 56px; border: 0; outline: none; text-decoration: none; border-radius: 18px;" />
                                                            </td>
                                                        </tr>
                                                    </table>
                                                    <p style="margin: 14px 0 0; font-family: Arial, Helvetica, sans-serif; font-size: 25px; line-height: 32px; font-weight: 700; color: #ffffff;">{$productName}</p>
                                                    <p style="margin: 8px 0 0; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 22px; color: #e8eaff;">{$tagline}</p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 36px 24px 24px;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse;">
                                                        <tr>
                                                            <td align="center" style="padding: 0 0 12px; font-family: Arial, Helvetica, sans-serif; font-size: 28px; line-height: 34px; font-weight: 700; color: #111827;">
                                                                {$title}
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td align="center" style="padding: 0 0 28px; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 27px; color: #6b7280;">
                                                                {$description}
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td align="center" style="padding: 0 0 20px;">
                                                                {$actionContent}
                                                            </td>
                                                        </tr>
                                                        {$metaHtml}
                                                        <tr>
                                                            <td style="padding: 12px 0 0;">
                                                                <div style="height: 1px; line-height: 1px; font-size: 1px; background-color: #e5e7eb;">&nbsp;</div>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td align="center" style="padding: 0 24px 32px; font-family: Arial, Helvetica, sans-serif; font-size: 13px; line-height: 21px; color: #9ca3af;">
                                                    {$footerText}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
            HTML;
    }

    private function renderVerificationAction(string $safeCode): string
    {
        return <<<HTML
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td align="center" style="padding: 0 0 10px; font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 18px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #6b7280;">
                        Verification code
                    </td>
                </tr>
                <tr>
                    <td align="center">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse;">
                            <tr>
                                <td align="center" style="padding: 20px 28px; border-radius: 20px; background-color: #eef2ff; font-family: 'Courier New', Courier, monospace; font-size: 36px; line-height: 36px; font-weight: 700; letter-spacing: 10px; color: #5856d6;">
                                    {$safeCode}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            HTML;
    }

    private function renderPasswordResetAction(string $safeResetUrl): string
    {
        return <<<HTML
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td align="center" style="padding: 0 0 10px; font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 18px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #6b7280;">
                        Password reset link
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding: 28px 20px; border-radius: 24px; background-color: #eef2ff;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin: 0 auto; border-collapse: separate;">
                            <tr>
                                <td align="center" bgcolor="#5856d6" style="border-radius: 999px; background-color: #5856d6;">
                                    <a href="{$safeResetUrl}" target="_blank" rel="noopener noreferrer" style="display: block; padding: 14px 24px; font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 20px; font-weight: 700; text-align: center; color: #ffffff; text-decoration: none; white-space: nowrap;">
                                        Reset password
                                    </a>
                                </td>
                            </tr>
                        </table>
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; border-collapse: collapse; margin-top: 18px;">
                            <tr>
                                <td align="center" style="padding: 0 0 10px; font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 18px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #6b7280;">
                                    Or paste this link into your browser
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 14px 16px; border-radius: 18px; background-color: #ffffff; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 24px; color: #5856d6; word-break: break-all;">
                                    <a href="{$safeResetUrl}" target="_blank" rel="noopener noreferrer" style="color: #5856d6; text-decoration: none;">{$safeResetUrl}</a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            HTML;
    }

    private function logoUrl(): string
    {
        return rtrim($this->brandingConfig->appBaseUrl(), '/') . '/apple-touch-icon.png';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
