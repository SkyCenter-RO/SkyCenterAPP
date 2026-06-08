#!/bin/sh
set -eu

test_database="${POSTGRES_DB}_test"

psql --set ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname postgres <<-SQL
    CREATE DATABASE "$test_database" OWNER "$POSTGRES_USER";
SQL
