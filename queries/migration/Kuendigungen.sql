SELECT * FROM (
	SELECT
		IF(vertraege.Vertragsende <= '1970-01-01',DATE(NOW()),DATE(DATE_SUB(vertraege.Vertragsende, INTERVAL KuendigungsfristInMon MONTH))) AS Kündigungstermin,
		IF(DATE_SUB(Vertragsende,INTERVAL KuendigungsfristInMon MONTH) <= '1970-01-01',DATE(DATE_ADD(NOW(), INTERVAL KuendigungsfristInMon MONTH)),Vertragsende) AS FruehestesEnde,
		vertraege.*,
		unterkuenfte.typ AS UnterkunftTyp,
		unterkuenfte.status, strasse, nr, etage, lage
	FROM vertraege
	LEFT JOIN unterkuenfte ON unterkuenfte.id = vertraege.unterkunft
) AS a
ORDER BY Kündigungstermin