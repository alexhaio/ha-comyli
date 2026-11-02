SELECT
	personen.AZLand,
    personen.Name,
    personen.Vorname,
    personen.Geburtsdatum,
    personen.Geschlecht,
    IF(`personen-unterkunft-history`.`Einzug` IS NULL,personen.EinzugAm,`personen-unterkunft-history`.`Einzug`) AS Einzug,
    CASE
    	WHEN `personen-unterkunft-history`.`Einzug` = '0000-00-00' THEN 'unbekannt'
    	WHEN uHis.ort IS NULL THEN uIs.ort
        ELSE uHis.ort
    END AS Ort,
    CASE
    	WHEN `personen-unterkunft-history`.`Einzug` = '0000-00-00' THEN ''
    	WHEN uHis.strasse IS NULL THEN uIs.strasse
        ELSE uHis.strasse
    END AS Strasse,
    CASE
    	WHEN `personen-unterkunft-history`.`Einzug` = '0000-00-00' THEN ''
    	WHEN uHis.nr IS NULL THEN uIs.nr
        ELSE uHis.nr
    END AS Nr,
    CASE
    	WHEN `personen-unterkunft-history`.`Einzug` = '0000-00-00' THEN ''
    	WHEN uHis.etage IS NULL THEN uIs.etage
        ELSE uHis.etage
    END AS Etage,
    CASE
    	WHEN `personen-unterkunft-history`.`Einzug` = '0000-00-00' THEN ''
    	WHEN uHis.lage IS NULL THEN uIs.lage
        ELSE uHis.lage
    END AS Lage
FROM `personen`
LEFT JOIN `personen-unterkunft-history` ON
	`personen-unterkunft-history`.Name = personen.Name
    AND `personen-unterkunft-history`.`Vorname`= personen.Vorname
    AND `personen-unterkunft-history`.`Geburtsdatum` = personen.Geburtsdatum
LEFT JOIN unterkuenfte AS uHis ON uHis.id = `personen-unterkunft-history`.`Unterkunft`
LEFT JOIN unterkuenfte AS uIs ON uIs.id = personen.unterkunftid
WHERE personen.geloescht = 0
ORDER BY personen.Name, personen.Vorname, `personen-unterkunft-history`.`Einzug`;