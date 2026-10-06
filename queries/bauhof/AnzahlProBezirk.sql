SELECT
	Bezirk,
	Count('BaumNr') AS AnzahlBaeume
FROM arbokat
WHERE gefaellt IS NULL
Group BY Bezirk