SELECT `Vitalitaet`, COUNT(`BaumNr`) AS AnzahlBaeume
FROM `arbokat`
WHERE gefaellt IS NULL
GROUP BY `Vitalitaet`