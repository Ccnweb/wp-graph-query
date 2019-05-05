<?php

require_once(__DIR__.'/lib.php');

class WpGraphQuery {
    
    // low level accepted types
    // 'date-string' is the mysql string format for dates : 'yyyy-mm-dd'
    private $root_types = ['string', 'integer', 'double', 'boolean', 'date-string', 'time', 'time-string', 'any'];

    
    private $DEBUG = false;
    private $logger = null; // to be injected at runtime
    
    // db info
    private $db;
	private $dbname_samuel;
    private $dbname_ojmj;
    private $bdd_samuel;
    private $bdd_ojmj;
    
    // these are useless, it's just FYI
    private $TableDossiers_fields = ['NomDossier', 'Designation', 'ParametresJ', 'LogementInfoJ'];
    private $FormulesPrix_fields = ['NomDossier', 'NumFormule', 'NumTransport', 'NumTarif', 'ProfilTarif', 'CodeDevise', 'Prix', 'PrixAR', 'Acompte', 'AcompteAR', 'DateApplication', 'ApplicableA'];
    
    // SQL queries
    private $sql_queries_str = [
        'dossiers'              => 'SELECT * FROM `TableDossiers`',
        'dossier_by_name'       => 'SELECT * FROM `TableDossiers` WHERE NomDossier = :dossier',
        'dossiers_of_formule'   => 'SELECT TableDossiers.NomDossier, TableDossiers.Designation, TableDossiers.ParametresJ, TableDossiers.LogementInfoJ
                                    FROM `TableDossiers`, `FormulesParametres`
                                    WHERE FormulesParametres.NomDossier = TableDossiers.NomDossier
                                        AND NumFormule = :NumFormule',

        'formules_of_dossier'   => "SELECT NomDossier, NumFormule, Pour, `Type`, Intitule, SIntitule, Precisions, `Description`, DateDebut, HeureDebut, DateFin, HeureFin FROM `FormulesParametres` AS Param
                                    LEFT JOIN `FormulesGroupes` AS Groupe USING(NomDossier,NumFormule)
                                    WHERE Param.NomDossier = :dossier AND Groupe.NumGroupe='1' AND Groupe.VisibiliteFormule='PU'",
        'formule_by_id'			=> "SELECT * FROM FormulesParametres WHERE NomDossier = :NomDossier AND NumFormule = :NumFormule",

        'transports_of_dossier' => 'SELECT DISTINCT idTransport, Designation, Nature, PtDepart, PtArrivee, Sens, TSDepart, TSArrivee, Capacite, Tarif, CodeDevise, DossierAffreteur, DossiersUtilisateurs, Precisions, Etat
                                    FROM `Transports`, `TransportsFormule` 
                                    WHERE 
                                        TransportsFormule.NomDossier = Transports.DossierAffreteur 
                                        AND Transports.DossierAffreteur = :NomDossier AND Etat <> "C"
                                        AND (
                                            TransportsFormule.ListeTransportsAller LIKE CONCAT("%", Transports.idTransport, "%")
                                            OR TransportsFormule.ListeTransportsRetour LIKE CONCAT("%", Transports.idTransport, "%")
                                        )',
        'transports_of_formule' => 'SELECT DISTINCT idTransport, Designation, Nature, PtDepart, PtArrivee, Sens, TSDepart, TSArrivee, Capacite, Tarif, CodeDevise, DossierAffreteur, DossiersUtilisateurs, Precisions, Etat
                                    FROM `Transports`, `TransportsFormule` 
                                    WHERE 
                                        TransportsFormule.NomDossier = Transports.DossierAffreteur 
                                        AND Transports.DossierAffreteur = :NomDossier AND Etat <> "C"
                                        AND (
                                            TransportsFormule.ListeTransportsAller LIKE CONCAT("%", Transports.idTransport, "%")
                                            OR TransportsFormule.ListeTransportsRetour LIKE CONCAT("%", Transports.idTransport, "%")
                                        )
                                        AND TransportsFormule.NumFormule = :NumFormule',
        'transport_by_id'       => 'SELECT * FROM Transports WHERE idTransport = :idTransport',

        'prix_of_formule'       => 'SELECT * FROM `FormulesPrix` WHERE NomDossier = :NomDossier AND NumFormule = :NumFormule',

        'paiements_of_dossier'   => 'SELECT * FROM `ParamPaiementEnLigne` WHERE Dossier = :NomDossier',

        'ojmj_pays'      		=> "SELECT * FROM `TablePays`",
        'ojmj_pays_by_code'     => "SELECT * FROM `TablePays` WHERE Code = :CountryCode",

        'ojmj_get_traductions_langs' => 'SELECT DISTINCT COLUMN_NAME
                                        FROM INFORMATION_SCHEMA.COLUMNS
                                        WHERE TABLE_NAME = "Traductions"',

        'traduction'           => "SELECT * FROM Traductions WHERE FR = :str",
        
    ];
    private $sql_queries = [];
    
    private $Types = [
        'Query' => [
            'Posts' =>  '[Post]',
            'Post'  =>  'Post',
            'User'  =>  'User',
        ],

        // https://developer.wordpress.org/reference/functions/get_post/
        'Post' => [
            'id'                => 'int',
            'type'              => 'string',
            'date'              => 'time',
            'date_gmt'          => 'time',
            'author'            => 'User',
            'title'             => 'string',
            'content'           => 'string',
            'excerpt'           => 'string',
            'status'            => 'string',
        ],

        // https://developer.wordpress.org/reference/functions/get_user_by/
        'User' => [
            "id"           => 'int',
            "login"        => 'string',
            "nicename"     => 'string',
            "email"        => 'string',
            "url"          => 'string',
            "registered"   => 'time-string',
            "status"       => 'string',
            "display_name" => 'string',
            "roles"        => 'any',
            "capabilities" => 'Capabilitiy',
        ],

        // https://codex.wordpress.org/Roles_and_Capabilities
        'Capabilitiy' => [
            // Super Admin
            "create_sites" => "bool",
            "delete_sites" => "bool",
            "manage_network" => "bool",
            "manage_sites" => "bool",
            "manage_network_users" => "bool",
            "manage_network_plugins" => "bool",
            "manage_network_themes" => "bool",
            "manage_network_options" => "bool",
            "upgrade_network" => "bool",
            "setup_network" => "bool",
            // Administrator
            "activate_plugins" => "bool",
            "delete_others_pages" => "bool",
            "delete_others_posts" => "bool",
            "delete_pages" => "bool",
            "delete_posts" => "bool",
            "delete_private_pages" => "bool",
            "delete_private_posts" => "bool",
            "delete_published_pages" => "bool",
            "delete_published_posts" => "bool",
            "edit_dashboard" => "bool",
            "edit_others_pages" => "bool",
            "edit_others_posts" => "bool",
            "edit_pages" => "bool",
            "edit_posts" => "bool",
            "edit_private_pages" => "bool",
            "edit_private_posts" => "bool",
            "edit_published_pages" => "bool",
            "edit_published_posts" => "bool",
            "edit_theme_options" => "bool",
            "export" => "bool",
            "import" => "bool",
            "list_users" => "bool",
            "manage_categories" => "bool",
            "manage_links" => "bool",
            "manage_options" => "bool",
            "moderate_comments" => "bool",
            "promote_users" => "bool",
            "publish_pages" => "bool",
            "publish_posts" => "bool",
            "read_private_pages" => "bool",
            "read_private_posts" => "bool",
            "read" => "bool",
            "remove_users" => "bool",
            "switch_themes" => "bool",
            "upload_files" => "bool",
            "customize" => "bool",
            "delete_site" => "bool",
            // Admin or Super admin in multisites
            "update_core" => "bool",
            "update_plugins" => "bool",
            "update_themes" => "bool",
            "install_plugins" => "bool",
            "install_themes" => "bool",
            "upload_plugins" => "bool",
            "upload_themes" => "bool",
            "delete_themes" => "bool",
            "delete_plugins" => "bool",
            "edit_plugins" => "bool",
            "edit_themes" => "bool",
            "edit_files" => "bool",
            "edit_users" => "bool",
            "create_users" => "bool",
            "delete_users" => "bool",
            "unfiltered_html" => "bool",
        ],
    ];
    private $Type_names = [];

    private $Resolvers = [];
    private $Resolver_names = [];

    // low level db info
    private $db_info = [];


    
    function __construct($dbname_samuel = '', $dbname_ojmj = '', $debug = false) {
        global $wpdb;
        $this->DEBUG = $debug;
        $this->initDb($dbname_samuel, $dbname_ojmj);

        $this->Resolvers = [

            'Query' => [
                'Posts' => function($p, $args) {
                    // we parse the $args and log the exact resulting WP_Query arguments
                    $q_args = graph_args_to_wp_args($args);
                    $this->log("INFO", "QUERY_POSTS", $q_args);

                    $wp_query = new WP_Query($q_args);
                    $my_posts = $wp_query->posts;
                    return $my_posts;
                },

                // get post by id or slug
                'Post' => function($parent, $args) use($wpdb) {
                    // allowed arguments : "id", "slug"
                    if (!empty($args['id'])) {
                        $post = get_post($args['id'], 'raw');
                        return ($post) ? $post->to_array(): null;
                    }
                    if (!empty($args['slug'])) {
                        $q_args = array(
                            'name'        => $args['slug'],
                            'numberposts' => 1
                        );
                        $my_posts = get_posts($q_args);
                        return ($my_posts) ? $my_posts[0] : null;
                    }
                    $res = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}posts WHERE post_id = ", OBJECT );
                    return $res;
                },

                // get user by id or ... 
                'User' => function($parent, $args) {
                    
                },
            ],

            // https://developer.wordpress.org/reference/functions/get_post/
            'Post' => [
                'id'        => function($parent, $args) {return $parent->ID;},
                'author'    => function($parent, $args = null) {
                                    return get_user_by('id', $parent->post_author);
                                },
                'title'     => function($parent, $args) {return $parent->post_title;},
                'content'   => function($parent, $args) {return $parent->post_content;},
                'type'      => function($parent, $args) {return $parent->post_type;},
                'date'      => function($parent, $args) {return $parent->post_date;},
                'date_gmt'  => function($parent, $args) {return $parent->post_date_gmt;},
                'excerpt'   => function($parent, $args) {return $parent->post_excerpt;},
                'status'    => function($parent, $args) {return $parent->post_status;},
            ],

            // https://developer.wordpress.org/reference/functions/get_user_by/
            // https://developer.wordpress.org/reference/classes/wp_user/
            'User' => [
                'id'            => function($p) {return (empty($p)) ? null : $p->get('ID');},
                'login'         => function($p) {return (empty($p)) ? null : $p->get('user_login');},
                'email'         => function($p) {return (empty($p)) ? null : $p->get('user_email');},
                "nicename"      => function($p) {return (empty($p)) ? null : $p->get('user_nicename');},
                "url"           => function($p) {return (empty($p)) ? null : $p->get('user_url');},
                "registered"    => function($p) {return (empty($p)) ? null : $p->get('user_registered');},
                "status"        => function($p) {return (empty($p)) ? null : $p->get('user_status');},
                "display_name"  => function($p) {return (empty($p)) ? null : $p->get('user_display_name');},
                "roles"         => function($p) {return (empty($p)) ? null : $p->get('roles');},
                "capabilities"  => function($p) {return (empty($p)) ? null : $p->get_role_caps();}
            ],

            // https://codex.wordpress.org/Roles_and_Capabilities
            "Capabilitiy" => [],

        ];

        $this->load_types();

        //$b = $this->check_internal_coherence(); // TODO improve this function and uncomment
        //if ($b !== true) throw new \Exception($b);
    }

    public function add_type($type_name, $type, $resolvers, $queries, $parent = null) {
        /**
         * Adds a new type along existing Post, User,... types
         * 
         * @param string    $type_name  type name like "Post"
         * @param array     $type       type definition like ['id' => 'int', author' => 'string']
         * @param array     $resolvers  resolvers definition like ['my_resolver_name' =>  function(){return 1;}, ...]
         * @param array     $queries    queries definition like ['my_query_name' => ['return' => 'Post', 'fn' => function(){return 1;}], ...]
         * @param string    $parent     (optional) parent type to inherit parent attributes and resolvers
         */

        // TODO check that everything is fine before adding the type
        if ($parent !== null && !in_array($parent, $this->Type_names)) return $this->error('PARENT_TYPE_NOT_FOUND', 'Parent type '.json_encode($parent).' could not be found', ['context' => 'WpGraphQuery > add_type']);

        // load parent type if needed
        $parent_type = ($parent !== null) ? $this->Types[$parent] : null;
        $parent_resolvers = ($parent !== null) ? $this->Resolvers[$parent] : null;

        // add type definition
        if ($parent_type) $type = array_merge_recursive($parent_type, $type);
        $this->Types[$type_name] = $type;

        // add resolvers
        $this->Resolvers[$type_name] = [];
        if ($parent_resolvers) $this->Resolvers[$type_name] = $parent_resolvers;
        foreach ($resolvers as $resolver_name => $resolver_fn) {
            $this->Resolvers[$type_name][$resolver_name] = $resolver_fn;
        }

        // add queries
        foreach ($queries as $query_name => $query_obj) {
            $this->Types['Query'][$query_name] = $query_obj['return'];
            $this->Resolvers['Query'][$query_name] = $query_obj['fn'];
        }
        // reload types
        $this->load_types();
    }

    private function load_types() {
        $this->Type_names = array_keys($this->Types);
        $this->Resolver_names = array_keys($this->Resolvers);
        sort($this->Type_names);
        sort($this->Resolver_names);
    }

    private function initDb() {
        // TODO ?
    }

    private function check_internal_coherence() {
        /**
         * Checks that types and resolvers are properly defined
         */

        if ($this->Type_names != $this->Resolver_names) return 'Types and Resolvers are not the same : Types = '.json_encode($this->Type_names)." and Resolvers = ".json_encode($this->Resolver_names);
        
        $b = lib\arrays_have_same_structure($this->Types, $this->Resolvers);
        if ($b !== true && !empty($b['missing1'])) return 'Missing fields in types : '.json_encode($b['missing1']);

        // Check Query is defined
        if (!isset($this->Types['Query'])) return 'Missing mandatory type "Query"';
        if (!isset($this->Resolvers['Query'])) return 'Missing resolver for "Query"';

        return true;
    }

    public function query($query) {
        /**
         * Main entry point to this class
         */

        if (!isset($query['Query'])) $query = ['Query' => $query];
        $res = $this->query_core($query);
        if (isset($res['Query'])) return $res['Query'];
        return $res;
    }

    public function query_simplify($query) {
        /**
         * Like the function query but simplifies the result
         * if each sub-array has a single element
         */

        $res = $this->query($query);
        return lib\array_simplify($res);
        /* $last_result = $res;
        $keys = array_keys($last_result);
        while (is_array($last_result) && count($keys) == 1) {
            $last_result = $last_result[$keys[0]];
            if (is_array($last_result)) $keys = array_keys($last_result);
        }

        // we further simplify if $last_result is a star array 


        return $last_result; */
    }

    private function query_core($query, $parent_data = null, $parent_type = '', $parent_type_solver = null) {
        /**
         * the function behind $this->query
         * 
         * example :
         * $query = [
         *      'Dossier(FRJEU)' => [
         *          'NomDossier',
         *          'Formules' => [
         *              'NumFormule',
         *              'Dossiers' => [
         *                  'NomDossier'
         *              ]
         *          ]
         *      ]
         * ]
         */
        $result = [];
        $success = true;

        // Get fields of the parent type
        $parent_fields = [];
        if ($parent_type != '') {
            $parent_fields = array_keys($this->Types[$parent_type]);
        }

        /* echo "\n\n====== QUERY ====== \n";
        echo "parent_data empty? ".(empty($parent_data)*1)."\n";
        echo "parent_type = ".$parent_type."\n";
        echo "parent_solvers = ".((is_array($parent_type_solver)) ? json_encode(array_keys($parent_type_solver)) : json_encode($parent_type_solver))."\n\n"; */

        // special case where $query = ['*'], we return all fields
        if ($query == ['*']) $query = array_keys($this->Types[$parent_type]);

        // Check that all fields are in parent_type
        // TODO

        // resolve sub queries
        foreach ($query as $query_name => $query_data) {

            /* echo "ITERATION : \n";
            echo "query_name = ".$query_name."\n";
            echo "query_data = ".json_encode($query_data)."\n\n"; */

            $query_name_info = parse_query_full_name($query_name);
            $current_field = $query_name_info['name']; 
            $args = $query_name_info['args'];

            /* echo "current_field = ".$current_field."\n";
            echo "args = ".json_encode($args)."\n\n"; */

            // resolve simple fields
            if (is_numeric($query_name) && in_array($query_data, $parent_fields)) {
                // check if there is a solver for this
                if (isset($parent_type_solver[$query_data])) {
                    $result[$query_data] = $parent_type_solver[$query_data]($parent_data, null);
                // else check if it's a simple case
                } else if (isset($parent_data[$query_data])) {
                    $result[$query_data] = $parent_data[$query_data];
                } else {
                    $result[$query_data] = null;
                }

            // resolve complex fields
            } else {
                // prepare current data
                $current_data = null;
                if (isset($parent_type_solver[$current_field])) {
                    $current_data = $parent_type_solver[$current_field]($parent_data, $args);
                }

                // check for a field in parent_type
                if (in_array($parent_type, $this->Type_names)) {
                    // we get the type of the current field
                    $current_type = $this->Types[$parent_type][$current_field];

                    // check if the current type is an array of elements of certain type
                    $return_array = false;
                    if ($current_type[0] == '[') {
                        $current_type = substr($current_type, 1, -1);
                        $return_array = true;
                    }
                    
                    
                    // case of a single element
                    if (!$return_array) {
                        $result[$current_field] = $this->query_core($query_data, $current_data, $current_type, $this->Resolvers[$current_type]);
                    // case of an array of elements
                    } else {
                        $result[$current_field] = [];
                        if (is_array($current_data)) {
                            foreach ($current_data as $data) {
                                $result[$current_field][] = $this->query_core($query_data, $data, $current_type, $this->Resolvers[$current_type]);
                            }
                        }
                    }


                // if there is no parent_type, it should be 
                } else if (isset($this->Resolvers[$current_field])) {
                    $result[$current_field] = $this->query_core($query_data, $parent_data, $current_field, $this->Resolvers[$current_field]);

                // error case
                } else {
                    $result[$current_field] = null;
                }
            }

            
        }
        return $result;
    }

    public function set_debug($bool) {
        /**
         * Sets the debug value to true or false
         */

        if ($this->DEBUG == $bool) return;
        $this->DEBUG = $bool;
        $this->initDb();
    }
    public function get_debug() {return $this->DEBUG;}

    public function set_logger($logger) {
        $this->logger = $logger;
    }
    private function log($level, $title, $data, $return_val = false) {
        if ($this->logger) {
            $title = "WpGraphQuery > ".$title;
            if (strtoupper($level) == "ERROR")      return $this->logger->error($title, $data, $return_val);
            if (strtoupper($level) == "WARNING")    return $this->logger->warning($title, $data, $return_val);
            if (strtoupper($level) == "INFO")       return $this->logger->info($title, $data, $return_val);
        }

        $body = (gettype($data) == 'string') ? $data : json_encode($data);
        $msg = date('Y-m-d H:i:s').' ::'.$level.'::'.strtoupper($title).':: '.$body."\n";
        echo $msg."\n";
        return $return_val;
    }

    private function error($title, $msg, $data = []) {
        if (is_array($msg)) $msg = implode(' ', $msg);
        $error = ['success' => false, 'error' => $title, 'description' => $msg];
        if (!empty($data)) $error['data'] = $data;
        return $error;
    }

    public function get_db_info() {
        return $this->db_info;
    }

}

?>