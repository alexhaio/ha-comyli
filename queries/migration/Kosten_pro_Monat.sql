SELECT 
    u.Ort, 
    u.Strasse, 
    u.nr AS Nr, 
    u.etage AS Etage, 
    u.lage AS Lage, 
    b.Typ,
    
    /* January */
    REPLACE(ROUND(IFNULL(v.V_01, 0), 2), '.', ',') AS `Januar Vertraege`,
    REPLACE(ROUND(IFNULL(r.R_01, 0), 2), '.', ',') AS `Januar Rechnungen`,
    REPLACE(ROUND(IFNULL(v.V_01, 0) + IFNULL(r.R_01, 0), 2), '.', ',') AS `Januar Gesamt`,

    /* February */
    REPLACE(ROUND(IFNULL(v.V_02, 0), 2), '.', ',') AS `Februar Vertraege`,
    REPLACE(ROUND(IFNULL(r.R_02, 0), 2), '.', ',') AS `Februar Rechnungen`,
    REPLACE(ROUND(IFNULL(v.V_02, 0) + IFNULL(r.R_02, 0), 2), '.', ',') AS `Februar Gesamt`,

    /* March */
    REPLACE(ROUND(IFNULL(v.V_03, 0), 2), '.', ',') AS `Maerz Vertraege`,
    REPLACE(ROUND(IFNULL(r.R_03, 0), 2), '.', ',') AS `Maerz Rechnungen`,
    REPLACE(ROUND(IFNULL(v.V_03, 0) + IFNULL(r.R_03, 0), 2), '.', ',') AS `Maerz Gesamt`,

    /* April */
    REPLACE(ROUND(IFNULL(v.V_04, 0), 2), '.', ',') AS `April Vertraege`,
    REPLACE(ROUND(IFNULL(r.R_04, 0), 2), '.', ',') AS `April Rechnungen`,
    REPLACE(ROUND(IFNULL(v.V_04, 0) + IFNULL(r.R_04, 0), 2), '.', ',') AS `April Gesamt`,

    /* May */
    REPLACE(ROUND(IFNULL(v.V_05, 0), 2), '.', ',') AS `Mai Vertraege`,
    REPLACE(ROUND(IFNULL(r.R_05, 0), 2), '.', ',') AS `Mai Rechnungen`,
    REPLACE(ROUND(IFNULL(v.V_05, 0) + IFNULL(r.R_05, 0), 2), '.', ',') AS `Mai Gesamt`,

    /* June */
    REPLACE(ROUND(IFNULL(v.V_06, 0), 2), '.', ',') AS `Juni Vertraege`,
    REPLACE(ROUND(IFNULL(r.R_06, 0), 2), '.', ',') AS `Juni Rechnungen`,
    REPLACE(ROUND(IFNULL(v.V_06, 0) + IFNULL(r.R_06, 0), 2), '.', ',') AS `Juni Gesamt`,

    /* July */
    REPLACE(ROUND(IFNULL(v.V_07, 0), 2), '.', ',') AS `Juli Vertraege`,
    REPLACE(ROUND(IFNULL(r.R_07, 0), 2), '.', ',') AS `Juli Rechnungen`,
    REPLACE(ROUND(IFNULL(v.V_07, 0) + IFNULL(r.R_07, 0), 2), '.', ',') AS `Juli Gesamt`,

    /* August */
    REPLACE(ROUND(IFNULL(v.V_08, 0), 2), '.', ',') AS `August Vertraege`,
    REPLACE(ROUND(IFNULL(r.R_08, 0), 2), '.', ',') AS `August Rechnungen`,
    REPLACE(ROUND(IFNULL(v.V_08, 0) + IFNULL(r.R_08, 0), 2), '.', ',') AS `August Gesamt`,

    /* September */
    REPLACE(ROUND(IFNULL(v.V_09, 0), 2), '.', ',') AS `September Vertraege`,
    REPLACE(ROUND(IFNULL(r.R_09, 0), 2), '.', ',') AS `September Rechnungen`,
    REPLACE(ROUND(IFNULL(v.V_09, 0) + IFNULL(r.R_09, 0), 2), '.', ',') AS `September Gesamt`,

    /* October */
    REPLACE(ROUND(IFNULL(v.V_10, 0), 2), '.', ',') AS `Oktober Vertraege`,
    REPLACE(ROUND(IFNULL(r.R_10, 0), 2), '.', ',') AS `Oktober Rechnungen`,
    REPLACE(ROUND(IFNULL(v.V_10, 0) + IFNULL(r.R_10, 0), 2), '.', ',') AS `Oktober Gesamt`,

    /* November */
    REPLACE(ROUND(IFNULL(v.V_11, 0), 2), '.', ',') AS `November Vertraege`,
    REPLACE(ROUND(IFNULL(r.R_11, 0), 2), '.', ',') AS `November Rechnungen`,
    REPLACE(ROUND(IFNULL(v.V_11, 0) + IFNULL(r.R_11, 0), 2), '.', ',') AS `November Gesamt`,

    /* December */
    REPLACE(ROUND(IFNULL(v.V_12, 0), 2), '.', ',') AS `Dezember Vertraege`,
    REPLACE(ROUND(IFNULL(r.R_12, 0), 2), '.', ',') AS `Dezember Rechnungen`,
    REPLACE(ROUND(IFNULL(v.V_12, 0) + IFNULL(r.R_12, 0), 2), '.', ',') AS `Dezember Gesamt`

FROM unterkuenfte u
JOIN (
    /* Create a cartesian product to link all accommodations with all existing Types */
    SELECT u2.id, t.Typ
    FROM unterkuenfte u2
    JOIN (
        SELECT Typ FROM rechnungen 
        UNION 
        SELECT Typ FROM vertraege
    ) t ON 1=1
) b ON u.id = b.id
LEFT JOIN (
    /* Pivot and sum up contracts by month for the current year */
    SELECT 
        unterkunft, 
        Typ,
        SUM(IF(vertragsbeginn <= LAST_DAY(CONCAT(YEAR(CURDATE()), '-01-01')) AND (vertragsende = '1970-01-01' OR vertragsende >= CONCAT(YEAR(CURDATE()), '-01-01')), BetragProMonatInEUR / IFNULL(NULLIF(faelligkeitInMon, 0), 1), 0)) AS V_01,
        SUM(IF(vertragsbeginn <= LAST_DAY(CONCAT(YEAR(CURDATE()), '-02-01')) AND (vertragsende = '1970-01-01' OR vertragsende >= CONCAT(YEAR(CURDATE()), '-02-01')), BetragProMonatInEUR / IFNULL(NULLIF(faelligkeitInMon, 0), 1), 0)) AS V_02,
        SUM(IF(vertragsbeginn <= LAST_DAY(CONCAT(YEAR(CURDATE()), '-03-01')) AND (vertragsende = '1970-01-01' OR vertragsende >= CONCAT(YEAR(CURDATE()), '-03-01')), BetragProMonatInEUR / IFNULL(NULLIF(faelligkeitInMon, 0), 1), 0)) AS V_03,
        SUM(IF(vertragsbeginn <= LAST_DAY(CONCAT(YEAR(CURDATE()), '-04-01')) AND (vertragsende = '1970-01-01' OR vertragsende >= CONCAT(YEAR(CURDATE()), '-04-01')), BetragProMonatInEUR / IFNULL(NULLIF(faelligkeitInMon, 0), 1), 0)) AS V_04,
        SUM(IF(vertragsbeginn <= LAST_DAY(CONCAT(YEAR(CURDATE()), '-05-01')) AND (vertragsende = '1970-01-01' OR vertragsende >= CONCAT(YEAR(CURDATE()), '-05-01')), BetragProMonatInEUR / IFNULL(NULLIF(faelligkeitInMon, 0), 1), 0)) AS V_05,
        SUM(IF(vertragsbeginn <= LAST_DAY(CONCAT(YEAR(CURDATE()), '-06-01')) AND (vertragsende = '1970-01-01' OR vertragsende >= CONCAT(YEAR(CURDATE()), '-06-01')), BetragProMonatInEUR / IFNULL(NULLIF(faelligkeitInMon, 0), 1), 0)) AS V_06,
        SUM(IF(vertragsbeginn <= LAST_DAY(CONCAT(YEAR(CURDATE()), '-07-01')) AND (vertragsende = '1970-01-01' OR vertragsende >= CONCAT(YEAR(CURDATE()), '-07-01')), BetragProMonatInEUR / IFNULL(NULLIF(faelligkeitInMon, 0), 1), 0)) AS V_07,
        SUM(IF(vertragsbeginn <= LAST_DAY(CONCAT(YEAR(CURDATE()), '-08-01')) AND (vertragsende = '1970-01-01' OR vertragsende >= CONCAT(YEAR(CURDATE()), '-08-01')), BetragProMonatInEUR / IFNULL(NULLIF(faelligkeitInMon, 0), 1), 0)) AS V_08,
        SUM(IF(vertragsbeginn <= LAST_DAY(CONCAT(YEAR(CURDATE()), '-09-01')) AND (vertragsende = '1970-01-01' OR vertragsende >= CONCAT(YEAR(CURDATE()), '-09-01')), BetragProMonatInEUR / IFNULL(NULLIF(faelligkeitInMon, 0), 1), 0)) AS V_09,
        SUM(IF(vertragsbeginn <= LAST_DAY(CONCAT(YEAR(CURDATE()), '-10-01')) AND (vertragsende = '1970-01-01' OR vertragsende >= CONCAT(YEAR(CURDATE()), '-10-01')), BetragProMonatInEUR / IFNULL(NULLIF(faelligkeitInMon, 0), 1), 0)) AS V_10,
        SUM(IF(vertragsbeginn <= LAST_DAY(CONCAT(YEAR(CURDATE()), '-11-01')) AND (vertragsende = '1970-01-01' OR vertragsende >= CONCAT(YEAR(CURDATE()), '-11-01')), BetragProMonatInEUR / IFNULL(NULLIF(faelligkeitInMon, 0), 1), 0)) AS V_11,
        SUM(IF(vertragsbeginn <= LAST_DAY(CONCAT(YEAR(CURDATE()), '-12-01')) AND (vertragsende = '1970-01-01' OR vertragsende >= CONCAT(YEAR(CURDATE()), '-12-01')), BetragProMonatInEUR / IFNULL(NULLIF(faelligkeitInMon, 0), 1), 0)) AS V_12
    FROM vertraege
    GROUP BY unterkunft, Typ
) v ON b.id = v.unterkunft AND b.Typ = v.Typ
LEFT JOIN (
    /* Pivot and sum up invoices by month for the current year */
    SELECT 
        unterkunft, 
        Typ,
        SUM(IF(MONTH(DokDatum) = 1, BetragInEUR, 0)) AS R_01,
        SUM(IF(MONTH(DokDatum) = 2, BetragInEUR, 0)) AS R_02,
        SUM(IF(MONTH(DokDatum) = 3, BetragInEUR, 0)) AS R_03,
        SUM(IF(MONTH(DokDatum) = 4, BetragInEUR, 0)) AS R_04,
        SUM(IF(MONTH(DokDatum) = 5, BetragInEUR, 0)) AS R_05,
        SUM(IF(MONTH(DokDatum) = 6, BetragInEUR, 0)) AS R_06,
        SUM(IF(MONTH(DokDatum) = 7, BetragInEUR, 0)) AS R_07,
        SUM(IF(MONTH(DokDatum) = 8, BetragInEUR, 0)) AS R_08,
        SUM(IF(MONTH(DokDatum) = 9, BetragInEUR, 0)) AS R_09,
        SUM(IF(MONTH(DokDatum) = 10, BetragInEUR, 0)) AS R_10,
        SUM(IF(MONTH(DokDatum) = 11, BetragInEUR, 0)) AS R_11,
        SUM(IF(MONTH(DokDatum) = 12, BetragInEUR, 0)) AS R_12
    FROM rechnungen
    WHERE YEAR(DokDatum) = YEAR(CURDATE())
    GROUP BY unterkunft, Typ
) r ON b.id = r.unterkunft AND b.Typ = r.Typ
/* Filter out combinations that have no costs in any month of the year */
WHERE 
    (IFNULL(v.V_01, 0) > 0 OR IFNULL(r.R_01, 0) > 0) OR
    (IFNULL(v.V_02, 0) > 0 OR IFNULL(r.R_02, 0) > 0) OR
    (IFNULL(v.V_03, 0) > 0 OR IFNULL(r.R_03, 0) > 0) OR
    (IFNULL(v.V_04, 0) > 0 OR IFNULL(r.R_04, 0) > 0) OR
    (IFNULL(v.V_05, 0) > 0 OR IFNULL(r.R_05, 0) > 0) OR
    (IFNULL(v.V_06, 0) > 0 OR IFNULL(r.R_06, 0) > 0) OR
    (IFNULL(v.V_07, 0) > 0 OR IFNULL(r.R_07, 0) > 0) OR
    (IFNULL(v.V_08, 0) > 0 OR IFNULL(r.R_08, 0) > 0) OR
    (IFNULL(v.V_09, 0) > 0 OR IFNULL(r.R_09, 0) > 0) OR
    (IFNULL(v.V_10, 0) > 0 OR IFNULL(r.R_10, 0) > 0) OR
    (IFNULL(v.V_11, 0) > 0 OR IFNULL(r.R_11, 0) > 0) OR
    (IFNULL(v.V_12, 0) > 0 OR IFNULL(r.R_12, 0) > 0)
ORDER BY u.Ort, u.Strasse, u.nr, b.Typ