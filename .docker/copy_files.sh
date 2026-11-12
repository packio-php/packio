#!/bin/sh
rm -rf ./build/
mkdir -p ./build/

RSYNC_EXCLUDES="
  --exclude=cache/.deptrac.cache
  --exclude=cache/.php-cs-fixer.cache
  --exclude=cache/.phpunit.cache/***
  --exclude=cache/cycle/schema.php
  --exclude=coverage/***
  --exclude=db/sqlite.db
  --exclude=logs/*.log
  --exclude=package-archives/***
  --include=rate-limits/.gitkeep
  --exclude=rate-limits/*
  --exclude=sessions/sess_*
  --exclude=ssr/***
"

for item in $(echo "$FOLDERS_TO_COPY" | tr "," "\n"); do
  # Get the parent directory for creating necessary folder structure
  parent_dir=$(dirname "./build/$item")

  # Create the parent directory if it doesn't exist
  mkdir -p "$parent_dir"

  if [ -d "./app/$item" ]; then
    # If it's a directory, copy its contents
    # shellcheck disable=SC2086
    rsync -aq $RSYNC_EXCLUDES "./app/$item/" "./build/$item/"
  elif [ -f "./app/$item" ]; then
    # If it's a file, copy the file
    rsync -aq "./app/$item" "./build/$item"
  else
    echo "Warning: ./app/$item does not exist!"
  fi
done
