CREATE TABLE "chwala_sessions" (
    "id"         varchar(40) NOT NULL,
    "uri"        varchar(1024) NOT NULL,
    "owner"      varchar(255) NOT NULL,
    "owner_name" varchar(255) DEFAULT NULL,
    "data"       long,
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
