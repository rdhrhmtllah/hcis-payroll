/*
Change ID: DDL-2026-006-add-provider-result-to-task-outbox
Target: Development database only; verify target before execution.
Purpose: Persist provider execution result separately from Cloud Task transport metadata.
*/

ALTER TABLE N_SYSTEM_Task_Outbox ADD
    Provider_Name varchar(100) NULL,
    Provider_Status varchar(50) NULL,
    Provider_Message_Id varchar(255) NULL,
    Provider_Response nvarchar(max) NULL,
    Processed_At datetime2(3) NULL,
    Sender_Employee_Code varchar(100) NULL,
    Source_Module varchar(100) NULL,
    Source_Feature varchar(150) NULL,
    Message_Template varchar(150) NULL;

CREATE INDEX IX_SYSTEM_Task_Outbox_Provider_Result
    ON N_SYSTEM_Task_Outbox (Provider_Name, Provider_Status, Processed_At, Id_Task_Outbox);

CREATE INDEX IX_SYSTEM_Task_Outbox_Source
    ON N_SYSTEM_Task_Outbox (Source_Module, Source_Feature, Message_Template, Id_Task_Outbox);
