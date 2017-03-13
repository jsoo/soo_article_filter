<?php

$plugin['version'] = '0.3.3';
$plugin['author'] = 'Jeff Soo';
$plugin['author_uri'] = 'http://ipsedixit.net/txp/';
$plugin['description'] = 'Create filtered list of articles before sending to txp:article or txp:article_custom';
$plugin['type'] = 0; 
$plugin['allow_html_help'] = 1;

if (! defined('txpinterface')) {
    global $compiler_cfg;
    @include_once('config.php');
    include_once($compiler_cfg['path']);
}

# --- BEGIN PLUGIN CODE ---

if(class_exists('\Textpattern\Tag\Registry')) {
    Txp::get('\Textpattern\Tag\Registry')
        ->register('soo_article_filter');
}

function soo_article_filter( $atts, $thing )
{
    global $pretext;
    if ($pretext['q']) return parse($thing);

    $customFields = getCustomFields();                                              // field names
    $customAtts = array_null(array_flip($customFields));
    $standardAtts = array(
        'expires'               => null,        // accept 'any', 'past', 'future', or 0
        'article_image' => null,        // boolean: 0 = no image, 1 = has image
        'multidoc'              => null,        // for soo_multidoc compatibility
        'index_ignore'  => 'a,an,the',  // leading words to move in index titles
        'index_field'   => null,        // custom field name for index-style title
        'update_set'    => null,        // SET clause for custom update query
        'update_where'  => null,        // WHERE clause for custom update query
        'where'                 => null,        // raw WHERE expression for filter
        'limit'                 => null,        // LIMIT value
        'offset'                => null,        // OFFSET value
        'sort'                  => null,        // ORDER BY expression
    );
    extract(lAtts($standardAtts + $customAtts, $atts));
    if (! is_null($expires)) {
        switch ( $expires ) {
            case 'any':
                $where_exp[] = 'Expires > 0';
                break;
            case 'past':
                $where_exp[] = 'Expires <= now() and Expires > 0';
                break;
            case 'future':
                $where_exp[] = 'Expires > now()';
                break;
            case 0:
                $where_exp[] = 'Expires = 0';
        }
    }

    if (! is_null($article_image))
        $where_exp[] = 'Image '.($article_image ? '!' : '')."= ''";

    if ($customFields)
        foreach( $customFields as $i => $field )
                    // to prevent conflicts between named atts and custom fields
            if ( ! array_key_exists($field, $standardAtts) and isset($atts[$field]) ) {
                $value = $atts[$field];
                switch ( $value ) {
                    case '':
                        $where_exp[] = "custom_$i = ''";
                        break;
                    default:
                        $where_exp[] = "custom_$i regexp '$value'";
                }
            }
    
    if ( $multidoc and _soo_multidoc_ids_init() ) {
            global $soo_multidoc;
            $where_exp[] = "ID not in (select id from " . safe_pfx('soo_multidoc') . " where id != root)";
    }
    
    if ($where) $where_exp[] = $where;
    
    $select = '*';
    $table = safe_pfx('textpattern');
    $where_exp = isset($where_exp) ? ' where '.implode(' and ', $where_exp) : '';
    $sort = $sort ? ' order by '.doSlash($sort) : '';
    $limit = $limit ? ' limit '.intval($offset).','.intval($limit) : '';
    
    if ($index_field) {
        $i = array_search($index_field, $customFields);
        if ( $i ) {
            $regexp = "'^(" . implode('|', do_list($index_ignore)) . ")$'";
            $select .= ", trim(Title) as index_title, substring_index(trim(Title),' ',1) as first_word, substring(trim(Title), locate(' ',trim(Title))+1) as remaining_words";
            $update[] = array(
                'set' => "custom_$i = concat(remaining_words, ', ', first_word)",
                'where' => "first_word regexp $regexp and custom_$i = ''",
            );
            $update[] = array(
                'set' => "custom_$i = trim(Title)",
                'where' => "custom_$i = ''",
            );
            
        }
    }
            
    if (! safe_query("create temporary table $table select $select from $table".$where_exp.$sort.$limit))
        return;
    
    if ($update_set)
        $update[] = array(
            'set' => $update_set,
            'where' => $update_where ? $update_where : '1=1',
        );
    
    if (! empty($update))
        foreach ($update as $u)
            safe_update('textpattern', $u['set'], $u['where']);
    
    $out = parse($thing);
    safe_query("drop temporary table $table");
    return $out;
}

# --- END PLUGIN CODE ---

?>
