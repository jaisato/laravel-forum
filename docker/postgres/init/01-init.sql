-- Runs once, when the postgres service starts on an empty volume
-- (compose.yaml): pgvector in the forum's database, and the database of the
-- test suite on PostgreSQL (`make test-pgsql`) with pgvector too.

CREATE EXTENSION IF NOT EXISTS vector;

CREATE DATABASE forum_testing;

\connect forum_testing

CREATE EXTENSION IF NOT EXISTS vector;
