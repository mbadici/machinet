CREATE TABLE "chwala_locks" (
    "uri"     varchar(512) NOT NULL,
    "owner"   varchar(256),
    "timeout" integer,
    "expires" timestamp DEFAULT NULL,
    "token"   varchar(256),
    "scope"   smallint,
    "depth"   smallint
);

CREATE INDEX "uri_index" ON "chwala_locks" ("uri", "depth");
CREATE INDEX "expires_index" ON "chwala_locks" ("expires");
CREATE INDEX "token_index" ON "chwala_locks" ("token");


CREATE TABLE "chwala_sessions" (
    "id"         varchar(40) NOT NULL,
    "uri"        varchar(1024) NOT NULL,
    "owner"      varchar(255) NOT NULL,
    "owner_name" varchar(255) DEFAULT NULL,
    "data"       long,
    "readonly"   smallint DEFAULT 0 NOT NULL,
    PRIMARY KEY ("id")
);

CREATE INDEX "chwala_sessions_uri_idx" ON "chwala_sessions" ("uri");
CREATE INDEX "chwala_sessions_owner_idx" ON "chwala_sessions" ("owner");


CREATE TABLE "chwala_invitations" (
    "session_id" varchar(40) NOT NULL
        REFERENCES "chwala_sessions" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
    "user"       varchar(255) NOT NULL,
    "user_name"  varchar(255) DEFAULT NULL,
    "status"     varchar(16) NOT NULL,
    "changed"    timestamp DEFAULT NULL,
    "comment"    long
);

CREATE INDEX "chwala_invitations_session_id_idx" ON "chwala_invitations" ("session_id");
CREATE UNIQUE INDEX "chwala_invitations_user_idx" ON "chwala_invitations" ("user", "session_id");

INSERT INTO "system" ("name", "value") VALUES ('chwala-version', '2016101700');
