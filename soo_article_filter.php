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

function soo_article_filter($atts, $thing)
{
    $customFields = getCustomFields();
    $customlAtts = array_null(array_flip($customFields));
    extract(lAtts(array(
        'expires'       => null,    // accept 'any', 'past', 'future', or 0
    ) + $customlAtts, $atts));
    
    if (! is_null($expires)) {
        switch ($expires) {
            case 'any':
                $where[] = 'Expires > 0';
                break;
            case 'past':
                $where[] = 'Expires <= now() and Expires > 0';
                break;
            case 'future':
                $where[] = 'Expires > now()';
                break;
            case 0:
                $where[] = 'Expires = 0';
        }
    }

    if ($customFields) {
        foreach($customFields as $i => $field) {
            if (isset($atts[$field])) {
                $value = $atts[$field];
                switch ($value) {
                    case '':
                        $where[] = "custom_$i = ''";
                        break;
                    default:
                        $where[] = "custom_$i regexp '$value'";
                }
            }
        }
    }
    
    $where = isset($where) ? ' where ' . implode(' and ', $where) : '';
    
    $table = safe_pfx('textpattern');
    safe_query("create temporary table $table select * from $table" . $where);
    $out = parse($thing);
    safe_query("drop temporary table $table");
    return $out;

}

# --- END PLUGIN CODE ---

?>
