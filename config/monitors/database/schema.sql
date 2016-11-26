
-- SQLITE SCHEMA

CREATE TABLE "device_snmp_historic" (
        `device`        TEXT NOT NULL,
        `date`  TEXT NOT NULL,
        `oid`   TEXT NOT NULL,
        `value` INTEGER NOT NULL,
        PRIMARY KEY(device,date,oid)
);
