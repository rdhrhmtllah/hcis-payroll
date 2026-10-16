/*
Change ID: DDL-2026-005-create-system-task-outbox
Target: Development database only; verify target before execution.
Purpose: Durable task intent/outbox for critical asynchronous work.
Table: N_SYSTEM_Task_Outbox
*/

CREATE TABLE N_SYSTEM_Task_Outbox (
    Id_Task_Outbox int IDENTITY(1,1) NOT NULL,
    Task_Type varchar(100) NOT NULL,
    Aggregate_Type varchar(100) NULL,
    Aggregate_Id int NULL,
    Idempotency_Key varchar(255) NOT NULL,
    Queue_Name varchar(100) NOT NULL,
    Task_Url varchar(500) NULL,
    Payload nvarchar(max) NULL,
    Status varchar(30) NOT NULL,
    Attempt_Count int NOT NULL CONSTRAINT DF_SYSTEM_Task_Outbox_Attempt_Count DEFAULT (0),
    Max_Attempts int NOT NULL CONSTRAINT DF_SYSTEM_Task_Outbox_Max_Attempts DEFAULT (3),
    Available_At datetime2(3) NOT NULL CONSTRAINT DF_SYSTEM_Task_Outbox_Available_At DEFAULT (SYSUTCDATETIME()),
    Locked_At datetime2(3) NULL,
    Locked_By varchar(150) NULL,
    Dispatched_At datetime2(3) NULL,
    Cloud_Task_Name varchar(500) NULL,
    Last_Error nvarchar(max) NULL,
    Created_At datetime2(3) NOT NULL CONSTRAINT DF_SYSTEM_Task_Outbox_Created_At DEFAULT (SYSUTCDATETIME()),
    Created_By varchar(100) NULL,
    Updated_At datetime2(3) NULL,
    Updated_By varchar(100) NULL,
    CONSTRAINT PK_SYSTEM_Task_Outbox PRIMARY KEY CLUSTERED (Id_Task_Outbox),
    CONSTRAINT UQ_SYSTEM_Task_Outbox_Idempotency_Key UNIQUE (Idempotency_Key)
);

CREATE INDEX IX_SYSTEM_Task_Outbox_Dispatch
    ON N_SYSTEM_Task_Outbox (Status, Available_At, Id_Task_Outbox)
    INCLUDE (Task_Type, Queue_Name, Attempt_Count, Max_Attempts);

CREATE INDEX IX_SYSTEM_Task_Outbox_Aggregate
    ON N_SYSTEM_Task_Outbox (Aggregate_Type, Aggregate_Id, Id_Task_Outbox);
