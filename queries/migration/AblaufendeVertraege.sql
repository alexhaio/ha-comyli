SELECT
	DATE_SUB(Vertragsende,INTERVAL KuendigungsfristInMon MONTH) AS Kündigungstermin,
	id, unterkuenfte.typ AS UnterkunftTyp, ort, strasse, nr, etage, lage, status, objektart, groesseInM2, info,
	Vertragsbeginn, Vertragsende, BetragProMonatInEUR, vertraege.Typ, Vertragspartner, KautionInEUR
FROM vertraege
LEFT JOIN unterkuenfte ON vertraege.unterkunft = unterkuenfte.id
WHERE DATE_SUB(Vertragsende,INTERVAL KuendigungsfristInMon+2 MONTH) < NOW()