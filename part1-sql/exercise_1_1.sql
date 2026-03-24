/*

a). List all active contracts with their client name, tariff code, and 
total kWh consumed in the current year. Order by total kWh descending. 
(Hint: you will need to JOIN multiple tables.)
*/

SELECT 
    cl.full_name,
    t.code,
    SUM(mr.kwh_consumed) AS total_kwh
FROM contracts c
JOIN clients cl ON c.client_id = cl.id
JOIN tariffs t ON c.tariff_id = t.id
-- Use LEFT JOIN in order to include the actived contrantcs without reading for
-- for this year
LEFT JOIN meter_readings mr ON c.id = mr.contract_id 
    AND YEAR(mr.reading_date) = YEAR(GETDATE())
WHERE c.status = 'active'
GROUP BY cl.full_name, t.code
ORDER BY total_kwh DESC;

/*
b). For each country ('ES' and 'PT'), find the total number of active contracts 
and the average monthly consumption (kWh) over the last 6 months.
*/

SELECT 
    cl.country,
    COUNT(DISTINCT c.id) AS total_active_contracts,
    AVG(monthly_usage.total_month_kwh) AS avg_monthly_consumption
FROM clients cl
JOIN contracts c ON cl.id = c.client_id
LEFT JOIN (
    SELECT 
        contract_id, 
--      Create format year-month to apply in group by       
        FORMAT(reading_date, 'yyyy-MM') AS month_key,
        SUM(kwh_consumed) AS total_month_kwh
    FROM meter_readings
    WHERE reading_date >= DATEADD(MONTH, -6, GETDATE())
--  The group by is for contract and year-month, to avoid multiples readings in the
--  same month
    GROUP BY contract_id, FORMAT(reading_date, 'yyyy-MM')
) AS monthly_usage ON c.id = monthly_usage.contract_id
WHERE c.status = 'active'
AND cl.country IN ('ES', 'PT')
GROUP BY cl.country;

/*
c). Find all clients who have at least one contract but have NEVER received an invoice. 
Return: client name, fiscal_id, and contract count.
*/

SELECT 
    cl.full_name,
    cl.fiscal_id,
    COUNT(c.id) AS contract_count
FROM clients cl
JOIN contracts c ON cl.id = c.client_id
LEFT JOIN invoices i ON c.id = i.contract_id
WHERE i.id IS NULL
GROUP BY cl.full_name, cl.fiscal_id;