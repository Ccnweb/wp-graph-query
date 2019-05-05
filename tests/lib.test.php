<?php

require_once __DIR__.'/test.php';
require_once __DIR__.'/../lib.php';


$all = [
    'test_explode_protected', 
    'test_parse_query_full_name',
    'test_graph_args_to_wp_args',
];

run_suite($all);

function test_graph_args_to_wp_args() {
    $label = 'T1 : get posts by ids';
    $args = ['ids' => [1,2,3]];
    $res = graph_args_to_wp_args($args);
    expect(json_encode($res), '{"post_type":"post","posts_per_page":-1,"post__in":[1,2,3]}', $label);

    $label = 'T2 : get posts by simple taxonomy + meta field';
    $args = [
        'type' => 'propositions',
        'tax' => [
            'theme' => 'intervenant'
        ],
        'meta' => [
            '_my_meta_field' => '421'
        ]
    ];
    $res = graph_args_to_wp_args($args);
    expect(json_encode($res), '{"post_type":"propositions","posts_per_page":-1,"tax_query":[{"taxonomy":"theme","field":"CHAR","terms":"intervenant","operator":"IN"}],"meta_query":{"relation":"AND","_my_meta_field_clause":{"key":"_my_meta_field","value":"421","compare":"=","type":"NUMERIC"}}}', $label);

    $label = 'T3 : get posts by complex taxonomy + meta field';
    $args = [
        'type' => 'propositions',
        'tax' => [
            'theme' => ['terms' => ['M. A', 'Mme B'], 'operator' => 'NOT IN', 'field' => 'DATE']
        ],
        'meta or not like' => [
            '_my_meta_field' => '421',
            '_field2' => 'coco'
        ]
    ];
    $res = graph_args_to_wp_args($args);
    expect(json_encode($res), '{"post_type":"propositions","posts_per_page":-1,"tax_query":[{"taxonomy":"theme","field":"DATE","terms":["M. A","Mme B"],"operator":"NOT IN"}],"meta_query":{"relation":"OR","_my_meta_field_clause":{"key":"_my_meta_field","value":"421","compare":"NOT LIKE","type":"NUMERIC"},"_field2_clause":{"key":"_field2","value":"coco","compare":"NOT LIKE","type":"CHAR"}}}', $label);
    
    $label = 'T4 : get posts by complex taxonomy + meta field';
    $args = [
        'type' => 'propositions',
        'tax not in' => [
            'theme' => ['M. A', 'Mme B'],
        ],
        'meta_or' => [
            'f1:>:DATE' => '421',
            'f2' => 'truc',
        ]
    ];
    $res = graph_args_to_wp_args($args);
    expect(json_encode($res), '{"post_type":"propositions","posts_per_page":-1,"tax_query":[{"taxonomy":"theme","field":"CHAR","terms":["M. A","Mme B"],"operator":"NOT IN"}],"meta_query":{"relation":"OR","f1_clause":{"key":"f1","value":"421","compare":">","type":"DATE"},"f2_clause":{"key":"f2","value":"truc","compare":"=","type":"CHAR"}}}', $label);

    //show($res);
}

function test_parse_query_full_name() {
    $res = parse_query_full_name('mafonction ( machin = {"aze": "re", "a":[1], "b": {"c":3}}, [], riri = gio)');
    expect(json_encode($res), '{"name":"mafonction","args":{"machin":{"aze":"re","a":[1],"b":{"c":3}},"0":[],"riri":"gio"}}');
}

function test_explode_protected() {
    $res = explode_protected(",", "a, b, (c, d, e), f");
    expect($res, ["a"," b"," (c, d, e)", " f"], 'T1');
    
    $res = explode_protected(",", "aa=2, , b={'a':5, 'b':{'c': [1,2,3], 'd':1}}, [], f", ['{}', '[]']);
    expect($res, ["aa=2"," "," b={'a':5, 'b':{'c': [1,2,3], 'd':1}}"," []"," f"], 'T2');
    
    $res = false;
    try {explode_protected("::", "anything");} catch(Exception $e) {$res = 1;}
    expect($res, 1, 'T3');
}

?>