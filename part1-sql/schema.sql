-- ============================================================================
-- FactorEnergia - Technical Assessment (Semi-Senior)
-- Database Schema - SQL Server
-- ============================================================================

CREATE TABLE clients (
    id              INT PRIMARY KEY IDENTITY(1,1),
    fiscal_id       VARCHAR(20) NOT NULL UNIQUE,   -- NIF / NIF portugues
    full_name       NVARCHAR(200) NOT NULL,
    email           VARCHAR(150),
    country         CHAR(2) NOT NULL,              -- 'ES' or 'PT'
    created_at      DATETIME2 DEFAULT GETDATE()
);

CREATE TABLE tariffs (
    id              INT PRIMARY KEY IDENTITY(1,1),
    code            VARCHAR(20) NOT NULL UNIQUE,   -- e.g. 'FIX_2024', 'INDEX_PT'
    description     NVARCHAR(200),
    price_per_kwh   DECIMAL(10,6) NOT NULL,
    fixed_monthly   DECIMAL(10,2) NOT NULL DEFAULT 0,
    country         CHAR(2) NOT NULL,
    active          BIT NOT NULL DEFAULT 1
);

CREATE TABLE contracts (
    id              INT PRIMARY KEY IDENTITY(1,1),
    client_id       INT NOT NULL REFERENCES clients(id),
    tariff_id       INT NOT NULL REFERENCES tariffs(id),
    cups            VARCHAR(25) NOT NULL,          -- supply point identifier
    start_date      DATE NOT NULL,
    end_date        DATE,                          -- NULL = still active
    status          VARCHAR(20) NOT NULL           -- 'active','cancelled','pending'
                    CHECK (status IN ('active','cancelled','pending')),
    created_at      DATETIME2 DEFAULT GETDATE()
);

CREATE TABLE meter_readings (
    id              INT PRIMARY KEY IDENTITY(1,1),
    contract_id     INT NOT NULL REFERENCES contracts(id),
    reading_date    DATE NOT NULL,
    kwh_consumed    DECIMAL(12,3) NOT NULL,
    source          VARCHAR(20) NOT NULL           -- 'smart_meter','manual','estimate'
                    CHECK (source IN ('smart_meter','manual','estimate')),
    created_at      DATETIME2 DEFAULT GETDATE()
);

CREATE TABLE invoices (
    id              INT PRIMARY KEY IDENTITY(1,1),
    contract_id     INT NOT NULL REFERENCES contracts(id),
    billing_period  VARCHAR(7) NOT NULL,           -- 'YYYY-MM'
    total_kwh       DECIMAL(12,3),
    total_amount    DECIMAL(10,2),
    status          VARCHAR(20) NOT NULL DEFAULT 'draft'
                    CHECK (status IN ('draft','issued','paid','overdue')),
    issued_at       DATETIME2,
    created_at      DATETIME2 DEFAULT GETDATE()
);
