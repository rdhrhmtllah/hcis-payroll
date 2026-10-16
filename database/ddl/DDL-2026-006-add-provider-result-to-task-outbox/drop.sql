/* Manual companion only. Never execute automatically. */
DROP INDEX IX_SYSTEM_Task_Outbox_Source ON N_SYSTEM_Task_Outbox;
DROP INDEX IX_SYSTEM_Task_Outbox_Provider_Result ON N_SYSTEM_Task_Outbox;
ALTER TABLE N_SYSTEM_Task_Outbox
    DROP COLUMN Provider_Name, Provider_Status, Provider_Message_Id, Provider_Response, Processed_At,
        Sender_Employee_Code, Source_Module, Source_Feature, Message_Template;
