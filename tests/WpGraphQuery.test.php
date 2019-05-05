<?php
// To run this file, launch a wamp server (not with flywheel, as it will be in docker)
// then execute :
// wp eval-file .\WpGraphQuery.test.php --path=C:\wamp64\www\wordpress 
// tu peux ajouter le paramètre --url=http://www.bethechurch.ccn pour spécifier un certain site du réseau multisite

require_once __DIR__.'/test.php';
require_once __DIR__.'/../WpGraphQuery.php';

global $wpdb;
$wp_gq = new WpGraphQuery();

$all = [
    'test_posts'
];

run_suite($all);

function test_posts() {
    global $wpdb, $wp_gq;
    $query = [
        "Post(id=4)" => [
            "id",
            "type",
            "author" => [
                'login',
                "email",
                "roles",
                "capabilities" => [
                    "switch_themes"
                ],
            ],
            "title",
            "content",
        ]
    ];
    $res = $wp_gq->query($query);
}

$query = [
    'Posts(ids=[4,9,12])' => [
        'id',
        'title',
    ]
];


// ============================================
//          HELPER FUNCTIONS
// ============================================

function insert_post($post = []) {

}

/* $wp_query = new WP_Query([
    'post_type' => 'post',
    'post__in'      => [4,9,'12'],
    //'post_status'   => 'publish',
    //'lang'          =>  pll_current_language(),
    'posts_per_page'=>  -1,
]);

$wp_posts = $wp_query->posts;
foreach ($wp_posts as $p) {
    echo "post ".$p->ID."\n";
} */
?>