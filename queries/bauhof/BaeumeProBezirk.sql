SELECT
	Bezirk,
	COUNT(`BaumNr`) AS AnzahlBaeume
FROM `arbokat`
GROUP BY Bezirk