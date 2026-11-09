<?php
namespace ha\CoMyLi;

defined('ABSPATH') or die('Nope.');

class LegacySpecifics {
    private ?Config $config;

    public function __construct(Config $config = null) {
        $this->config = $config;
    }

    public function register(): void {
        // Allgemeine Fallback-Einstellungen für alle Tabellen
        add_filter('comyli_config', [$this, 'applyGlobalSettings'], 10, 2);
        
        // Spezifische Einstellungen pro Tabelle
        add_filter('comyli_config_arbokat', [$this, 'applyArbokat'], 10, 1);
        add_filter('comyli_config_wp_ha_sync_phonebook', [$this, 'applyPhonebook'], 10, 1);
        add_filter('comyli_config_wp_ha_verfahrensliste_edv_rpa', [$this, 'applyVerfahrensliste'], 10, 1);
        add_filter('comyli_config_wp_ha_ask_user_for_information', [$this, 'applyAskUser'], 10, 1);
        add_filter('comyli_config_wp_ha_fuehrerscheinstelle_gebuehrenliste', [$this, 'applyFuehrerscheinstelle'], 10, 1);
    }
	
    /**
     * @param array<string, mixed> $config
     * @param string $stdTbl
     * @return array<string, mixed>
     */
    public function applyGlobalSettings(array $config, string $stdTbl): array {
        $config['valueH']['lastChangedUser'] = wp_get_current_user()->user_login;
        $config['readonly']['created'] = true;
        $config['postOverride']['created'] = gmdate("Y-m-d H:i:s");
        $config['readonly']['createdUser'] = true;
        $config['valueP']['createdUser'] = wp_get_current_user()->user_login;
		
		
        $fachdienste = json_decode(get_option('ha_ask_user_for_information_fachdienst', '{}'));
        $config['foreignKey']['list']['fachdienst'] = [];
        foreach ((array)$fachdienste as $data) {
            if (isset($data->fachdienst)) {
                $config['foreignKey']['list']['fachdienst'][] = $data->fachdienst;
            }
        }
        $config['foreignKey']['input']['Fachdienst'] = 'fachdienst';
		
        return $config;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function applyArbokat(array $config): array {
        $config['queries'] = './wp-content/plugins/ha-comyli/queries/bauhof/';
        
        $config['foreignKey']['list']['Bezirk'] = 'SELECT * FROM tbl_bezirke';
        $config['foreignKey']['input']['Bezirk'] = 'Bezirk';
        
        $config['foreignKey']['list']['ArtLateinisch'] = 'SELECT ArtLat FROM tbl_baumarten';
        $config['foreignKey']['input']['ArtLateinisch'] = 'ArtLateinisch';
        
        $config['foreignKey']['list']['BewertungWurzel'] = 'SELECT * FROM tbl_bewertungwurzel';
        $config['foreignKey']['input']['BewertungWurzel'] = 'BewertungWurzel';
        
        $config['foreignKey']['list']['BewertungStammKrone'] = 'SELECT * FROM tbl_bewertungstamm_krone';
        $config['foreignKey']['input']['BewertungStamm'] = 'BewertungStammKrone';
        $config['foreignKey']['input']['BewertungKrone'] = 'BewertungStammKrone';
        
        $config['foreignKey']['list']['SymptomeKrone'] = 'SELECT * FROM tbl_symptomekrone';
        $config['foreignKey']['input']['SymptomeKrone'] = 'SymptomeKrone';
        
        $config['foreignKey']['list']['Vitalitaet'] = 'SELECT * FROM tbl_vitalitaet';
        $config['foreignKey']['input']['Vitalitaet'] = 'Vitalitaet';
        
        $config['foreignKey']['list']['Physiologie'] = 'SELECT * FROM tbl_physiologie';
        $config['foreignKey']['input']['Physiologie'] = 'Physiologie';
        
        $config['foreignKey']['list']['Verkehrssicherheit'] = 'SELECT * FROM tbl_verkehrssicherheit';
        $config['foreignKey']['input']['Verkehrssicherheit'] = 'Verkehrssicherheit';
        
        $config['foreignKey']['list']['naechsteKontrolle'] = 'SELECT * FROM tbl_naechstekontrolldaten';
        $config['foreignKey']['input']['naechsteKontrolle'] = 'naechsteKontrolle';
        
        $config['foreignKey']['list']['MassnahmenStandort'] = 'SELECT * FROM tbl_massnahmenstandort';
        $config['foreignKey']['input']['MassnahmenStandort'] = 'MassnahmenStandort';
        
        $config['foreignKey']['list']['StandortAusfuehrung'] = 'SELECT * FROM tbl_ausfuehrung';
        $config['foreignKey']['input']['StandortAusfuehrung'] = 'StandortAusfuehrung';
        $config['foreignKey']['input']['MassnahmenAusfuehrung'] = 'StandortAusfuehrung';
        
        $config['foreignKey']['list']['MassnahmenBaum'] = 'SELECT * FROM tbl_massnahmenbaum';
        $config['foreignKey']['input']['MassnahmenBaum'] = 'MassnahmenBaum';
    
        $current_user = wp_get_current_user();
        $readonly = !in_array('arbokat_write', (array) $current_user->roles);
        $first_name = get_user_meta($current_user->ID, 'first_name', true);
        $last_name  = get_user_meta($current_user->ID, 'last_name', true);
        $full_name = trim("$last_name, $first_name");
        
        $config['readonly']['Bezirk'] = $readonly;
        $config['readonly']['BaumNr'] = $readonly;
        $config['readonly']['Ort'] = $readonly;
        $config['readonly']['GenauerStandort'] = $readonly;
        $config['readonly']['ArtLateinisch'] = $readonly;
        $config['readonly']['Durchmesser'] = $readonly;
        $config['readonly']['Umfang'] = $readonly;
        $config['readonly']['Kronendurchmesser'] = $readonly;
        $config['readonly']['Hoehe'] = $readonly;
        $config['readonly']['gefaellt'] = $readonly;
        
        $config['valueH']['Bearbeiter'] = $full_name;
        if (isset($config['mysqli'])) {
            $result = $config['mysqli']->query("SELECT IF(MAX(BaumNr) IS NULL,1,MAX(BaumNr)+1) FROM arbokat");
            $row = $result ? $result->fetch_row() : null;
            $config['value']['BaumNr'] = $row[0] ?? 1;
            
            for ($y = 0; $y < 3; $y++) {
                for ($m = 1; $m <= 4; $m++) {
                    $config['mysqli']->query("INSERT IGNORE INTO `tbl_naechstekontrolldaten` VALUES('" . (gmdate("Y") + $y) . "-0" . $m . "-01')");
                }
            }
        }
        
        $config['param']['Durchmesser'] = ' onInput="umfang(this)"';
        $config['param']['Umfang'] = ' onInput="durchmesser(this)"';
        
        return $config;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function applyPhonebook(array $config): array {
        $config['queries'] = './wp-content/plugins/ha-comyli/queries/telefonverzeichnis/';
        $config['stdQry'] = 'index';
        return $config;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function applyVerfahrensliste(array $config): array {
        $fachdienste = json_decode(get_option('ha_ask_user_for_information_fachdienst', '{}'));
        $config['foreignKey']['list']['fachdienst'] = [];
        foreach ((array)$fachdienste as $data) {
            if (isset($data->fachdienst)) {
                $config['foreignKey']['list']['fachdienst'][] = $data->fachdienst;
            }
        }
        $config['foreignKey']['input']['FDs_test'] = 'fachdienst';
        $isExport = (isset($_GET['Y3N2']) && $_GET['Y3N2'] === '1') || (isset($_GET['aW1n']) && $_GET['aW1n'] === '1') || !empty($_GET['csv']);
        
        if (
            current_user_can('stabsstelledigitalisierung') ||
            current_user_can('edv') ||
            current_user_can('rechnungspruefung') ||
            current_user_can('datenschutz')
        ) {
            // Administrator-like access
            if (!$isExport) {
                echo '<style>
                .comyli .td:nth-of-type(4) { width: 600px; }
                /* Horizontally Sticky Columns */
                .comyli .td:nth-of-type(1) { width: 80px; min-width: 80px; max-width: 80px; position: sticky; left: 0; z-index: 6; background-color: inherit; border-right: 1px solid #e2e8f0; }
                .comyli .td:nth-of-type(2) { position: sticky; left: 80px; z-index: 6; background-color: inherit; border-right: 2px solid #cbd5e1; box-shadow: 2px 0 5px rgba(0,0,0,0.05); }
                .comyli .td { max-width: 400px; }
                #colNames > .td:nth-of-type(1), #colNames > .td:nth-of-type(2) { z-index: 12; }
            </style>';
            }
        } else {
            $config['edit'] = 0;
        
            if (current_user_can('fuehrungskraefte')) {
                $config['limit_cols']['wp_ha_verfahrensliste_edv_rpa'] = [
                    "Name_des_Verfahrens" => true, #2
                    "Beschreibung" => true, #3
                    "KI" => true, #4
                    "Nutzungshinweise" => true, #5
                    "Link" => true, #6
                    "Verfahrensadministrator" => true, #7
                    "Verfahrensverantwortlicher" => true, #8
                    "Fachdienste" => true, #9
                    "Finanzvolumen" => true, #10
                    "Anzahl_Transaktionen" => true, #11
                    "Anzahl_Benutzer" => true, #12
                    "Zertifizierungen" => true, #13
                    "Vorverfahren" => true, #14
                    "Schnittstellen zu anderen Systemen" => true, #15
                    "Anwenderdokumentation" => true, #16
                    "Verfahrensaufruf" => true, #17
                    "LeikaID" => true, #18
                    "Kosten" => true, #19
                    "Bemerkungen" => true, #20
                ];
                if (!$isExport) {
				echo '<style>
					/* Horizontally Sticky Columns */
					.comyli .td:nth-of-type(1) { width: 80px; min-width: 80px; max-width: 80px; position: sticky; left: 0; z-index: 6; background-color: inherit; border-right: 1px solid #e2e8f0; }
					.comyli .td:nth-of-type(2) { position: sticky; left: 80px; z-index: 6; background-color: inherit; border-right: 2px solid #cbd5e1; box-shadow: 2px 0 5px rgba(0,0,0,0.05); }
					
					.comyli .td:nth-of-type(4) { max-width: 100px; } /* KI */
					.comyli .td:nth-of-type(5) { max-width: 400px; } /* Nutzungshinweise */
					.comyli .td:nth-of-type(6) { max-width: 200px; } /* Link */
					.comyli .td:nth-of-type(7) { max-width: 400px; } /* Verfahrensadministrator */
					.comyli .td:nth-of-type(13), .comyli .td:nth-of-type(14), .comyli .td:nth-of-type(15), .comyli .td:nth-of-type(16) { width: 400px; }
				</style>';
                }
			} else {
				$config['limit_cols']['wp_ha_verfahrensliste_edv_rpa'] = [
					"Name_des_Verfahrens" => true,
					"Beschreibung" => true,
					"KI" => true,
					"Nutzungshinweise" => true,
					"Link" => true,
					"Fachdienste" => true,
				];
                if (!$isExport) {
				echo '<style>
					/* Horizontally Sticky Columns */
					.comyli .td:nth-of-type(1) { width: 80px; min-width: 80px; max-width: 80px; position: sticky; left: 0; z-index: 6; background-color: inherit; border-right: 1px solid #e2e8f0; }
					.comyli .td:nth-of-type(2) { position: sticky; left: 80px; z-index: 6; background-color: inherit; border-right: 2px solid #cbd5e1; box-shadow: 2px 0 5px rgba(0,0,0,0.05); }
					
					.comyli .td:nth-of-type(4) { max-width: 50px; } /* KI */
					/*.comyli .td:nth-of-type(5) { max-width: 400px; }*/ /* Nutzungshinweise */
					.comyli .td:nth-of-type(6) { max-width: 200px; } /* Link */
				</style>';
                }
            }
        }
        return $config;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function applyAskUser(array $config): array {
        global $wpdb;
        $fachdienste = json_decode(get_option('ha_ask_user_for_information_fachdienst', '{}'));
        $config['foreignKey']['list']['fachdienst'] = [];
        foreach ((array)$fachdienste as $data) {
            if (isset($data->fachdienst)) $config['foreignKey']['list']['fachdienst'][] = $data->fachdienst;
        }
        $config['foreignKey']['input']['fachdienst'] = 'fachdienst';

        $sachgebiete = json_decode(get_option('ha_ask_user_for_information_sachgebiet', '{}'));
        $config['foreignKey']['list']['sachgebiet'] = [];
        foreach ((array)$sachgebiete as $data) {
            if (isset($data->sachgebiet)) $config['foreignKey']['list']['sachgebiet'][] = $data->sachgebiet;
        }
        $config['foreignKey']['input']['Sachgebiet'] = 'sachgebiet';

        $standorte = json_decode(get_option('ha_ask_user_for_information_standort', '{}'));
        $config['foreignKey']['list']['standort'] = [];
        foreach ((array)$standorte as $data) {
            if (isset($data->standort)) $config['foreignKey']['list']['standort'][] = $data->standort;
        }
        $config['foreignKey']['input']['Ort'] = 'standort';

        $positionsbezeichnungen = $wpdb->get_results("SELECT `name` FROM `wp_ha_ask_user_positionsbezeichnungen`;");
        $config['foreignKey']['list']['positionsbezeichnungen'] = [];
        foreach ((array)$positionsbezeichnungen as $data) {
            if (isset($data->name)) $config['foreignKey']['list']['positionsbezeichnungen'][] = $data->name;
        }
        $config['foreignKey']['input']['Positionsbezeichnung'] = 'positionsbezeichnungen';

        $users = get_users();
        $config['foreignKey']['list']['username'] = [""];
        foreach ($users as $user) {
            $config['foreignKey']['list']['username'][] = $user->user_login;
        }
        $config['foreignKey']['input']['username'] = 'username';
        
        return $config;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function applyFuehrerscheinstelle(array $config): array {
        #$config['sqlLog'] = "./wp-content/plugins/ha-comyli/log/fuehrerscheinstelle.log";
        $config['queries'] = './wp-content/plugins/ha-comyli/queries/fuehrerscheinstelle/';
        $config['value']['Datum'] = gmdate('Y-m-d');
        $config['value']['Gebuehren'] = "0.00";
        $config['param']['Gebuehren'] = "min='0.00'";
        $config['value']['FAER'] = "0.00";
        $config['param_step']['FAER'] = '3.30';
        $config['param']['FAER'] = "min='0.00'";
        $config['value']['ZFER_Probe'] = "0.00";
        $config['param_step']['ZFER_Probe'] = '1.80';
        $config['param']['ZFER_Probe'] = "min='0.00' max='1.80'";
        $config['value']['ZFER'] = "0.00";
        $config['value']['Status'] = "offen";
        $config['param']['ZFER'] = "min='0.00'";
        
        $config['value']['Px'] = chr(ord('A') + (gmdate("Y") + 26 - 2024) % 26);
        
        if (isset($config['mysqli'])) {
            $result = $config['mysqli']->query("SELECT SQL_NO_CACHE nr FROM wp_ha_fuehrerscheinstelle_gebuehrenliste WHERE Px='" . $config['value']['Px'] . "' ORDER BY nr DESC LIMIT 1");
            $nrRes = $result ? $result->fetch_row() : null;
            $nr = str_pad((string)(($nrRes[0] ?? 0) + 1), 4, "0", STR_PAD_LEFT);
            $config['value']['nr'] = $nr;
            $config['postOverride']['nr'] = $nr;
            
            $columns = [];
            $result = $config['mysqli']->query("SELECT Wohnort FROM wp_ha_fuehrerscheinstelle_gebuehrenliste GROUP BY Wohnort");
            $rows = $result ? $result->fetch_all() : [];
            foreach ($rows as $row) { $columns[] = $row[0]; }
            $config['foreignKey']['list']['Wohnort'] = $columns;
            $config['foreignKey']['suggestion']['Wohnort'] = 'Wohnort';
            
            $columns = [];
            $result = $config['mysqli']->query(
                "SELECT id
                FROM wp_ha_zentrale_rechnungsnr
                LEFT JOIN wp_ha_fuehrerscheinstelle_gebuehrenliste ON wp_ha_zentrale_rechnungsnr.id = wp_ha_fuehrerscheinstelle_gebuehrenliste.Beleg
                WHERE
                    wp_ha_zentrale_rechnungsnr.fd = 'strassenverkehr'
                    AND wp_ha_fuehrerscheinstelle_gebuehrenliste.Beleg IS NULL
                    AND wp_ha_zentrale_rechnungsnr.created < NOW() - INTERVAL 2 WEEK
                ORDER BY wp_ha_zentrale_rechnungsnr.created DESC"
            );
            $rows = $result ? $result->fetch_all() : [];
            foreach ($rows as $row) { $columns[] = $row[0]; }
            $config['foreignKey']['list']['Beleg'] = $columns;
            $config['foreignKey']['suggestion']['Beleg'] = 'Beleg';
        }
        
        $config['gui']['insert_top'] = 1;
        
        if (!isset($_GET['b3JkZXI']) && !isset($_GET['cXJ5'])) {
            $_GET['b3JkZXI'] = 'created';
            $_GET['ZA'] = 'DESC';
        }
        
        return $config;
    }

    /**
     * Migrates and synchronizes legacy serialized table configurations in post_content
     * with individual _comyli_* post meta fields.
     */
    public function migrateConfigs(): void {
        $postType = $this->config ? ($this->config->plugin['type'] ?? 'comyli') : 'comyli';
        $posts = get_posts([
            'post_type'      => $postType,
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'draft', 'pending', 'private'],
        ]);

        foreach ($posts as $post) {
            $data = [];
            $unserialized = maybe_unserialize($post->post_content);
            if (is_array($unserialized)) {
                $data = $unserialized;
            }

            $meta = get_post_meta($post->ID);
            foreach ($meta as $k => $v) {
                if (str_starts_with($k, '_comyli_')) {
                    $key = substr($k, 8);
                    $data[$key] = maybe_unserialize($v[0]);
                }
            }

            if (empty($data)) {
                continue;
            }

            foreach ($data as $key => $val) {
                update_post_meta($post->ID, '_comyli_' . $key, $val);
            }

            wp_update_post([
                'ID'           => $post->ID,
                'post_content' => wp_slash(serialize($data)),
            ]);
        }
    }
}
