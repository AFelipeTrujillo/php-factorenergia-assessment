CREATE PROCEDURE sp_GenerateInvoice
    @contract_id INT,
    @billing_period VARCHAR(7) -- Format 'YYYY-MM'
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        BEGIN TRANSACTION;

        IF NOT EXISTS (SELECT 1 FROM contracts WHERE id = @contract_id AND status = 'active')
        BEGIN
            THROW 50001, 'Contract does not exist or is not active.', 1;
        END

        -- There is no need to cast the date; the billing_period format is 'YYYY-MM'. 
        IF EXISTS (SELECT 1 FROM invoices WHERE contract_id = @contract_id AND billing_period = @billing_period)
        BEGIN
            THROW 50002, 'An invoice already exists for this contract and period.', 1;
        END

        DECLARE @total_kwh DECIMAL(12,3);
        DECLARE @price_per_kwh DECIMAL(10,6);
        DECLARE @fixed_monthly DECIMAL(10,2);
        DECLARE @total_amount DECIMAL(10,2);

        -- Get consumption data
        SELECT @total_kwh = SUM(kwh_consumed)
        FROM meter_readings
        WHERE contract_id = @contract_id
            AND FORMAT(reading_date, 'yyyy-MM') = @billing_period;

        IF @total_kwh IS NULL
        BEGIN
            THROW 50003, 'No readings found for the period.', 1;
        END

        IF @total_kwh < 0
        BEGIN
            THROW 50005, 'Negative consumption', 1;
        END

        -- Get tariff details
        SELECT 
            @price_per_kwh = t.price_per_kwh,
            @fixed_monthly = t.fixed_monthly
        FROM contracts c
        JOIN tariffs t ON c.tariff_id = t.id
        WHERE c.id = @contract_id;

        IF @price_per_kwh IS NULL OR @fixed_monthly IS NULL
        BEGIN
            THROW 50004, 'Price or Tariff not configured.', 1;
        END

        -- Calculate total: (kWh * price) + fixed fee
        SET @total_amount = (@total_kwh * @price_per_kwh) + @fixed_monthly;

        -- Insert Invoice 
        INSERT INTO invoices (contract_id, billing_period, total_kwh, total_amount, status, created_at)
        VALUES (@contract_id, @billing_period, @total_kwh, @total_amount, 'draft', GETDATE());

        -- Return the created invoice data
        -- Use the SCOPE_IDENTITY function to return the last ID generated in this session. 
        SELECT * FROM invoices WHERE id = SCOPE_IDENTITY();

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        -- Validate if a transaction exists, then rollback.
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    END CATCH
END