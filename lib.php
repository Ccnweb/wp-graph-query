<?php

function graph_args_to_wp_args($args) {
    /**
     * Parses arguments used in WpGraphQuery to arguments understandable by WP_Query
     * TODO rewrite this with model builder
     */

    $q_args = ['post_type' => 'post', 'posts_per_page' => -1];
    if (!empty($args['ids'])) $q_args['post__in'] = $args['ids'];
    if (!empty($args['type'])) $q_args['post_type'] = $args['type'];
    if (!empty($args['lang'])) $q_args['lang'] = ($args["lang"] == '_') ? pll_current_language() : $args['lang'];
    if (!empty($args['status'])) $q_args['post_status'] = $args['status'];
    if (!empty($args['posts_per_page'])) $q_args['posts_per_page'] = $args['posts_per_page'];
    if (!empty($args['category'])) $q_args['category__in'] = (is_numeric($args['category'])) ? [$args['category']] : [category_slug_to_id($args['category'], "not_found")];
    if (!empty($args['categories'])) $q_args['category__in'] = array_map(function($cat) {return (is_numeric($cat)) ? $cat : category_slug_to_id($cat, 'not_found');}, $args['categories']);
    
    // === Order By ===
    if (!empty($args['meta_key'])) {
        $q_args['meta_key'] = $args['meta_key'];
        $q_args['orderby'] = 'meta_value'; // 'meta_value' | 'meta_value_num'
    }
    if (!empty($args['order_by'])) $args['orderby'] = $args['order_by'];
    if (!empty($args['orderby'])) {
        $q_args['orderby'] = $args['orderby'];
        $q_args['order'] = 'ASC';
    }
    if (!empty($args['order'])) $q_args['order'] = $args['order'];
    
    $guess_type = function($el, $opt = []) {
        $opt = array_merge([
            '_default' => 'CHAR',
            '_dict_type_val' => 'value',
            'NUMERIC' => 'NUMERIC', 'CHAR' => 'CHAR'
        ], $opt);
        $opt_type = $opt['_dict_type_val'];
        if (is_numeric($el) || (is_array($el) && !empty($el[0]) && is_numeric($el[0])) ) {
            return $opt['NUMERIC'];
        } else if (is_array($el) && !empty($el[$opt_type]) ) {
            $thing = $el[$opt_type];
            if (is_numeric($thing) || is_array($thing) && is_numeric($thing[0])) return $opt['NUMERIC'];
        }
        return $opt['_default'];
    };

    // === Taxonomies ===
    // source : https://codex.wordpress.org/Class_Reference/WP_Query au paragraphe "Taxonomy Parameters"
    // example1 : $args['tax'] = ['theme' => ['theme1-slug', 3], 'machin' => 2]
    // example2 : $args['tax'] => ['theme' => ['field' => 'slug', 'terms' => 3, 'operator' => 'IN'], ...]
    // example3 : $args['tax_not_in'] => ['theme' => ['theme1-slug', 'theme2-slug']]
    $tax_keys = preg_array_keys("/^tax(_|\s|:|$)/", $args);
    if (!empty($tax_keys)) {
        $args_tax = $args[$tax_keys[0]];
        // get operator depending on tax_key
        preg_match("/^tax[_\s:](.+)$/", $tax_keys[0], $match_operator);
        $operator = ($match_operator && count($match_operator) > 1) ? strtoupper(preg_replace('/[_:]/', ' ', $match_operator[1])) : 'IN';
        if (!in_array($operator, ['IN', 'NOT IN', 'AND', 'EXISTS', 'NOT EXISTS'])) $operator = 'IN';

        if (empty($q_args['tax_query'])) $q_args['tax_query'] = [];
        foreach ($args_tax as $tax_name => $tax_val) {
            if (empty($tax_val)) continue;
            $field_guess = $guess_type($tax_val, ['_dict_type_val' => 'terms', 'NUMERIC' => 'id', 'CHAR' => 'slug']);//(is_numeric($tax_val) || (is_array($tax_val) && is_numeric($tax_val[0])) ) ? 'id' : 'slug';
            if (one_of_keys($tax_val, ['operator', 'field', 'terms', 'include_children'])) {
                $q_args['tax_query'][] = array_merge(['taxonomy' => $tax_name, 'field' => $field_guess], $tax_val);
                continue;
            }
            $q_args['tax_query'][] = [
                'taxonomy' => $tax_name,
                'field' => $field_guess,
                'terms' => $tax_val,
                'operator' => $operator, // 'IN', 'NOT IN', 'AND', 'EXISTS' and 'NOT EXISTS'. Default value is 'IN'
            ];
        }
    }

    // === Meta ===
    // source : https://codex.wordpress.org/Class_Reference/WP_Meta_Query
    $meta_keys = preg_array_keys("/^meta(_|\s|:|$)/", $args);
    if (!empty($meta_keys)) {
        $valid_meta_compare = ['=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS', 'REGEXP', 'NOT REGEXP', 'RLIKE'];
        $valid_meta_types = ['NUMERIC', 'BINARY', 'CHAR', 'DATE', 'DATETIME', 'DECIMAL', 'SIGNED', 'TIME', 'UNSIGNED'];

        $args_meta = $args[$meta_keys[0]];
        // get meta relation and compare mode
        preg_match("/^meta[_\s:](.+)$/", $meta_keys[0], $match_meta);
        $meta_relation = 'AND';
        $meta_compare = '=';
        if ($match_meta && count($match_meta) > 1) {
            $m = strtoupper($match_meta[1]);
            if (strpos($m, 'OR') !== false) $meta_relation = 'OR';
            $m = preg_replace('/[_\s:]*(OR|AND)[_\s:]*/', '', $m);
            $m = preg_replace('/[_:]/', ' ', $m);
            if (in_array($m, $valid_meta_compare)) $meta_compare = $m;
        }
        $operator = ($match_operator && count($match_operator) > 1) ? strtoupper(str_replace('_', ' ', $match_operator[1])) : 'IN';

        if (empty($q_args['meta_query'])) $q_args['meta_query'] = [];
        if (empty($q_args['meta_query']['relation'])) $q_args['meta_query']['relation'] = $meta_relation; // AND | OR
        $original_meta_compare = $meta_compare;
        foreach ($args_meta as $meta_name => $meta_val) {
            $meta_compare = $original_meta_compare;
            $type_guess = $guess_type($meta_val);

            // use normal wp syntax
            if (one_of_keys($tax_val, ['key', 'value', 'compare', 'type'])) {
                $q_args['meta_query'][$meta_name.'_clause'] = array_merge(['key' => $meta_name, 'type' => $type_guess], $meta_val);
                continue;
            }

            // parse meta_name like field_name:>:NUMERIC
            $meta_name_split = explode(':', $meta_name);
            $meta_name = $meta_name_split[0];
            if (count($meta_name_split) > 1 && in_array($meta_name_split[1], $valid_meta_compare)) $meta_compare = $meta_name_split[1];
            if (count($meta_name_split) > 1 && in_array($meta_name_split[1], $valid_meta_types)) $type_guess = $meta_name_split[1];
            if (count($meta_name_split) > 2 && in_array($meta_name_split[2], $valid_meta_compare)) $meta_compare = $meta_name_split[2];
            if (count($meta_name_split) > 2 && in_array($meta_name_split[2], $valid_meta_types)) $type_guess = $meta_name_split[2];

            $q_args['meta_query'][$meta_name.'_clause'] = [
                'key' => $meta_name,
                'value' => $meta_val,
                'compare' => strtoupper($meta_compare), //  '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS', 'REGEXP', 'NOT REGEXP', 'RLIKE'
                'type' => strtoupper($type_guess), // 'NUMERIC', 'BINARY', 'CHAR', 'DATE', 'DATETIME', 'DECIMAL', 'SIGNED', 'TIME', 'UNSIGNED'. Default value is 'CHAR'
            ];
        }
    }

    // add original wordpress query_args
    if (!empty($args['query_args'])) $q_args = array_merge_recursive($q_args, $args['query_args']);

    return $q_args;
}

function parse_query_full_name($query_full_name) {
    /**
     * Parses a full query name 
     */

    preg_match("/^([^\(]+)\(([^\)]+)\)/", $query_full_name, $matches);

    $args = (count($matches) > 1) ? array_map(function($v) {return trim($v);}, explode_protected(',', $matches[2], ['{}', '[]'])) : [];
    
    // tries to get named parameters
    $new_args = [];
    foreach ($args as $arg) {
        if (strpos($arg, '=')) {
            [$k, $v] = explode('=', $arg, 2);
            $new_args[trim($k)] = trim($v);
        } else {
            $new_args[] = trim($arg);
        }
    }
    // try to json decode when possible
    $new_args = array_map(function($arg) {
        if (preg_match("/^[\[\{]/", $arg)) {
            $res = json_decode($arg);
            if ($res !== null) return $res;
            return $arg;
        }
        return $arg;
    }, $new_args);
    
    return [
        'name' => (count($matches) > 0) ? trim($matches[1]): trim($query_full_name),
        'args' => $new_args,
    ];
}

// =========================================
//          WORDPRESS HELPER FUNS
// =========================================

function category_slug_to_id($cat_slug, $falsy_return = false) {
    /**
     * returns the category id corresponding to the category slug
     */

    $cat_original = get_category_by_slug( 'horaires' );
    if ($cat_original) return $cat_original->term_id;
    return $falsy_return;
}

// =========================================
//          MISC HELPER FUNS
// =========================================

function one_of($return_val = false) {
    /**
     * returns the first non-empty argument
     * returns $return_val if everything is false
     */

    $arg_list = func_get_args();
    foreach ($arg_list as $arg) {
        if (!empty($arg)) return $arg;
    }
    return $return_val;
}

// =========================================
//          DICT HELPER FUNS
// =========================================

function one_of_keys($arr, $keys, $return_val = false) {
    foreach ($keys as $k) {
        if (!empty($arr[$k])) return $arr[$k];
    }
    return $return_val;
}

function preg_array_keys($pattern, $array) {
    /**
     * Returns keys in $arr that match $pattern
     * @param string    $pattern
     * @param array     $array
     */
    $keys = array_keys($array);    
    return array_values(preg_grep($pattern, $keys));
}

// =========================================
//          STRING HELPER FUNS
// =========================================

function explode_protected($delim, $str, $protectors = ['()']) {
    /**
     * like explode function but it will protect delimiters that are protected by $protector
     * /!\ this does not check if the protectors are balanced !
     * /!\ only works for delimiters of length 1 !
     * 
     * look in tests/lib.test.php for examples
     */

    $mem = 0;
    $result = [];
    
    // prepare delimiters
    $delims = (is_array($delim)) ? $delim : [$delim];
    foreach ($delims as $d) if (strlen($d) > 1) throw new Exception("In explode_protected, all delimiters should be of length 1 !");

    $protect_starts = array_map(function($pr) {return $pr[0];}, $protectors);
    $protect_ends = array_map(function($pr) {return $pr[1];}, $protectors);

    $new_str = '';
    for ($i = 0; $i < strlen($str); $i++) {
        $c = $str[$i];
        if (in_array($c, $protect_starts)) {
            $new_str .= $c;
            $mem++;
        } else if (in_array($c, $protect_ends)) {
            $new_str .= $c;
            $mem--;
        } else if (in_array($c, $delims)) {
            if ($mem == 0) {
                $result[] = $new_str;
                $new_str = '';
            } else {
                $new_str .= $c;
            }
        } else $new_str .= $c;
    }
    if ($new_str != '') $result[] = $new_str;
    return $result;
}

?>