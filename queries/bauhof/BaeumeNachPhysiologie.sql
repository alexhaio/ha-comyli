SELECT
	`Physiologie`,
	COUNT(`BaumNr`) AS AnzahlBaeume
FROM `arbokat`
WHERE gefaellt IS NULL
GROUP BY `Physiologie`