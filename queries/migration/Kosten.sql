";
$von = $_SESSION['von'];
$bis = $_SESSION['bis'];
$qry="
SELECT strasse,nr, etage, lage, final.*
FROM (
		SELECT *
		FROM (
			/*Verbinden der Vertragsdaten mit den Rechnungsdaten*/
			SELECT
				alle.unterkunft,
				alle.Typ,
				REPLACE(ROUND(MAX(IFNULL(VertraegeImZeitraum,0)),2),'.',',') AS VertraegeImZeitraum,
				REPLACE(SUM(ROUND(IFNULL(rechnungen.BetragInEUR,0),2)),'.',',') AS RechnungenImZeitraum,
				REPLACE(ROUND(
					MAX(IFNULL(VertraegeImZeitraum,0)) +
					SUM(ROUND(IFNULL(rechnungen.BetragInEUR,0),2))
				,2),'.',',') AS Gesamt,
				'".$von."' AS von,
				'".$bis."' AS bis,
				alle.GU,
				alle.Info
			FROM (
				/*Kartesisches Produkt unterkunften * Typen um alle Verträge und Rechnungen damit verbinden zu können*/
				SELECT
					id AS unterkunft,
					typen.Typ,
					unterkuenfte.typ AS GU,
					info
				FROM unterkuenfte
				RIGHT JOIN (
					SELECT * FROM (
						SELECT Typ FROM rechnungen UNION SELECT Typ FROM vertraege
					) AS typen
					GROUP BY Typ
				) AS typen ON true
				
			) AS alle
			LEFT JOIN (
				/*Zusammenfassen der Vertragsdaten*/
				SELECT
					unterkunft,
					Typ,
					SUM(CEIL(relevZeitraum/faelligkeitInMon)*BetragProMonatInEUR) AS VertraegeImZeitraum
				FROM (
					/*Ermitteln der Vertragswerte im angegebenen Zeitraum*/
					SELECT
						unterkunft,
						Typ,
						BetragProMonatInEUR,
						faelligkeitInMon,
						TIMESTAMPDIFF(MONTH,effVon,effBis) AS relevZeitraum
					FROM (
						/*effektiver Berechnungszeitraum wird ermittelt*/
						SELECT
							unterkunft,
							Typ,
							BetragProMonatInEUR,
							faelligkeitInMon,
							vertragsbeginn,
							vertragsende,
							'".$von."' AS von,
							'".$bis."' AS bis,
							CAST(DATE_FORMAT(IF(vertragsbeginn > '".$von."',vertragsbeginn,'".$von."') ,'%Y-%m-01') as DATE) AS effVon,
							LAST_DAY(ADDDATE(IF(korEnde < '".$bis."',korEnde,'".$bis."'), INTERVAL 1 MONTH)) AS effBis
						FROM (
							/*01.01.1970 wird zu 'unbefristet'*/
							SELECT *, IF(vertragsende = '1970-01-01', ADDDATE('".$bis."', INTERVAL 1 MONTH), vertragsende) AS korEnde FROM vertraege
						) as vertraege
					) AS vertraege
				) as vertragswerte
				WHERE relevZeitraum > 0
				GROUP BY unterkunft, Typ
			) as vertragsZus ON alle.unterkunft = vertragsZus.unterkunft AND alle.Typ = vertragsZus.Typ
			/*Relevante rechnungsdaten auswählen*/
			/* LEFT JOIN rechnungen ON alle.unterkunft = rechnungen.unterkunft AND alle.Typ = rechnungen.Typ AND rechnungen.RDatum BETWEEN '".$von."' AND ADDDATE('".$bis."', INTERVAL 1 MONTH) */
			LEFT JOIN rechnungen ON alle.unterkunft = rechnungen.unterkunft AND alle.Typ = rechnungen.Typ AND rechnungen.RDatum BETWEEN '".$von."' AND '".$bis."'
			GROUP BY alle.unterkunft, alle.Typ
		) AS gesamt
		WHERE VertraegeImZeitraum <> '0,00' OR RechnungenImZeitraum <> '0,00'
	/* Vereinigen der Ergebnisse */
	UNION ALL
		/*Hinzufügen einer Gesamt-Zeile für jede unterkunft durch wiederholen der Abfrage und vereinigen der Ergebnisse*/
		SELECT unterkunft, 'Gesamt' AS Typ, SUM(VertraegeImZeitraum), SUM(RechnungenImZeitraum), SUM(Gesamt), von, bis, GU, Info
		FROM (
			/*Verbinden der Vertragsdaten mit den Rechnungsdaten*/
			SELECT
				alle.unterkunft,
				alle.Typ,
				REPLACE(ROUND(MAX(IFNULL(VertraegeImZeitraum,0)),2),'.',',') AS VertraegeImZeitraum,
				REPLACE(SUM(ROUND(IFNULL(rechnungen.BetragInEUR,0),2)),'.',',') AS RechnungenImZeitraum,
				REPLACE(ROUND(
					MAX(IFNULL(VertraegeImZeitraum,0)) +
					SUM(ROUND(IFNULL(rechnungen.BetragInEUR,0),2))
				,2),'.',',') AS Gesamt,
				'".$von."' AS von,
				'".$bis."' AS bis,
				alle.GU,
				alle.Info
			FROM (
				/*Kartesisches Produkt unterkunften * Typen um alle Verträge und Rechnungen damit verbinden zu können*/
				SELECT
					id AS unterkunft,
					typen.Typ,
					unterkuenfte.typ AS GU,
					info
				FROM unterkuenfte
				RIGHT JOIN (
					SELECT * FROM (
						SELECT Typ FROM rechnungen UNION SELECT Typ FROM vertraege
					) AS typen
					GROUP BY Typ
				) AS typen ON true
			) AS alle
			LEFT JOIN (
				/*Zusammenfassen der Vertragsdaten*/
				SELECT
					unterkunft,
					Typ,
					SUM(CEIL(relevZeitraum/faelligkeitInMon)*BetragProMonatInEUR) AS VertraegeImZeitraum
				FROM (
					/*Ermitteln der Vertragswerte im angegebenen Zeitraum*/
					SELECT
						unterkunft,
						Typ,
						BetragProMonatInEUR,
						faelligkeitInMon,
						TIMESTAMPDIFF(MONTH,effVon,effBis) AS relevZeitraum
					FROM (
						/*effektiver Berechnungszeitraum wird ermittelt*/
						SELECT
							unterkunft,
							Typ,
							BetragProMonatInEUR,
							faelligkeitInMon,
							vertragsbeginn,
							vertragsende,
							'".$von."' AS von,
							'".$bis."' AS bis,
							CAST(DATE_FORMAT(IF(vertragsbeginn > '".$von."',vertragsbeginn,'".$von."') ,'%Y-%m-01') as DATE) AS effVon,
							/*IF(ADDDATE(korEnde, INTERVAL 1 MONTH) < ADDDATE('".$bis."', INTERVAL 1 MONTH),ADDDATE(korEnde, INTERVAL 1 MONTH),ADDDATE('".$bis."', INTERVAL 1 MONTH)) AS effBis*/
							LAST_DAY(ADDDATE(IF(korEnde < '".$bis."',korEnde,'".$bis."'), INTERVAL 1 MONTH)) AS effBis
						FROM (
							/*01.01.1970 wird zu 'unbefristet'*/
							SELECT *, IF(vertragsende = '1970-01-01', ADDDATE('".$bis."', INTERVAL 1 MONTH), vertragsende) AS korEnde FROM vertraege
						) as vertraege
					) AS vertraege
				) as vertragswerte
				WHERE relevZeitraum > 0
				GROUP BY unterkunft, Typ
			) as vertragsZus ON alle.unterkunft = vertragsZus.unterkunft AND alle.Typ = vertragsZus.Typ
			/*Relevante rechnungsdaten auswählen*/
			/* LEFT JOIN rechnungen ON alle.unterkunft = rechnungen.unterkunft AND alle.Typ = rechnungen.Typ AND rechnungen.RDatum BETWEEN '".$von."' AND ADDDATE('".$bis."', INTERVAL 1 MONTH) */
			LEFT JOIN rechnungen ON alle.unterkunft = rechnungen.unterkunft AND alle.Typ = rechnungen.Typ AND rechnungen.RDatum BETWEEN '".$von."' AND '".$bis."'
			GROUP BY alle.unterkunft, alle.Typ
		) as gesamt
		GROUP BY unterkunft
) AS final
LEFT JOIN unterkuenfte ON final.unterkunft = unterkuenfte.id
ORDER BY final.unterkunft, final.Typ