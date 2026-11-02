SELECT
	unterkuenfte.ort,
	unterkuenfte.strasse,
	unterkuenfte.nr,
	unterkuenfte.etage,
	unterkuenfte.lage,
	vertraege.*
FROM vertraege
LEFT JOIN unterkuenfte ON vertraege.unterkunft = unterkuenfte.id
WHERE
	Vertragspartner LIKE '%Stadtwerke%'
	OR vertragspartner LIKE '%E.ON%'
	OR vertragspartner LIKE '%KEAM%'