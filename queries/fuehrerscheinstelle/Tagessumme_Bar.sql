SELECT
	Datum,
	SUM(Gebuehren*Bar_Gebuehren) AS Gebuehren,
	SUM(FAER*Bar_FAER) AS FAER,
	SUM(ZFER_Probe*Bar_ZFER_Probe) AS ZFER_Probe,
	SUM(ZFER*Bar_ZFER) AS ZFER,
	SUM(Gebuehren*Bar_Gebuehren + FAER*Bar_FAER + ZFER_Probe*Bar_ZFER_Probe + ZFER*Bar_ZFER) AS Summe
FROM wp_ha_fuehrerscheinstelle_gebuehrenliste
GROUP BY Datum
ORDER BY Datum DESC