SELECT
	Anrede AS Geschlecht,
	COUNT(Anrede) AS Anzahl
FROM [QRY]sprachkurseAllgemeineIntegrationskurse[/QRY]
GROUP BY Anrede