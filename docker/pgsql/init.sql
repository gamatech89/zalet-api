-- PostgreSQL initialization script
-- This runs when the container is first created

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- Create testing database
CREATE DATABASE uzivo_testing;
GRANT ALL PRIVILEGES ON DATABASE uzivo_testing TO uzivo;
