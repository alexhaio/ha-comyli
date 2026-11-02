SELECT
	CONCAT('<a href=\'./printArbeitsauftrag.php?unterkunft=',unterkunft,'&amp;datum=',datum,'\' target=\'_blank\'>',unterkunft,'</a>') AS unterkunft,
	datum,
	SUBSTRING(auszufuehrendeArbeiten,1,40) AS auszufuehrendeArbeiten,
	fertigstellungBis,
	materialwert,
	stundenlohn,
	anzahlStunden,
	pdf
FROM arbeitsauftrag