	SELECT
		CONCAT('Mn.Standort: ',IF(MassnahmenStandort='','keine',MassnahmenStandort)) AS Handlungsbedarf,
		COUNT(`BaumNr`) AS AnzahlBaeume
	FROM `arbokat`
	WHERE gefaellt IS NULL
	GROUP BY MassnahmenStandort
UNION
	SELECT
		CONCAT('Mn.Baum: ',IF(MassnahmenBaum='','keine',MassnahmenBaum)),
		COUNT(`BaumNr`)
	FROM `arbokat`
	WHERE gefaellt IS NULL
	GROUP BY MassnahmenBaum
UNION
	SELECT
		CONCAT('Mn.Ausfuehrung: ',IF(MassnahmenAusfuehrung='','keine',MassnahmenAusfuehrung)),
		COUNT(`BaumNr`)
	FROM `arbokat`
	WHERE gefaellt IS NULL
	GROUP BY MassnahmenAusfuehrung
UNION
	SELECT
		CONCAT('StandortAusfuehrung: ',IF(StandortAusfuehrung='','keine',StandortAusfuehrung)),
		COUNT(`BaumNr`)
	FROM `arbokat`
	WHERE gefaellt IS NULL
	GROUP BY StandortAusfuehrung