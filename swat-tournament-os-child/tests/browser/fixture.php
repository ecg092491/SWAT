<?php
/** Local-only fixture renderer using the actual child templates. NOT WordPress integration. */
if ( ! in_array( PHP_SAPI, array('cli', 'cli-server'), true ) || getenv('SWAT_FIXTURE_ENABLED') !== '1' ) { http_response_code(404); exit; }
define('ABSPATH', __DIR__ . '/');
$theme = realpath(getenv('SWAT_FIXTURE_THEME') ?: __DIR__ . '/../..');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path === '/style.css') { header('Content-Type: text/css'); readfile($theme . '/style.css'); exit; }
if (strpos($path, '/assets/') === 0) {
    $file=realpath($theme . $path);
    if (!$file || strpos($file, $theme . '/assets/') !== 0) { http_response_code(404); exit; }
    $ext=pathinfo($file,PATHINFO_EXTENSION);
    header('Content-Type: ' . (array('css'=>'text/css','js'=>'text/javascript','ttf'=>'font/ttf','woff2'=>'font/woff2')[$ext] ?? 'application/octet-stream'));
    readfile($file);exit;
}
$case = $_GET['case'] ?? 'pregame';
if (preg_match('#/__fixture/([^/]+)/#',$path,$m)) $case=$m[1];
$today=(new DateTimeImmutable('now',new DateTimeZone('America/New_York')))->format('Y-m-d');
$tomorrow=(new DateTimeImmutable('tomorrow',new DateTimeZone('America/New_York')))->format('Y-m-d');
$team='Winston-Salem Elite Baseball Academy Black';
$base=array('id'=>101,'playbook_id'=>'101','away'=>$team,'home'=>'Lexington Bolts','away_id'=>'11','home_id'=>'22','date'=>$today,'time'=>'23:00','field'=>'SW 1','division'=>'12U Open','pool_name'=>'Pool A','event'=>'SWAT Fixture Invitational','event_slug'=>'fixture-event','status'=>'Scheduled','away_score'=>null,'home_score'=>null,'game_type_id'=>1);
$games=array($base,array_merge($base,array('id'=>102,'playbook_id'=>'102','time'=>'23:45','field'=>'SW 7','home'=>'Triad Thunder')),array_merge($base,array('id'=>103,'playbook_id'=>'103','date'=>$tomorrow,'home'=>'Carolina Stars')));
if (in_array($case,array('live','final','sync-failed','stale'),true)) $games[0]=array_merge($games[0],array('status'=>$case==='final'?'Final':'Live','away_score'=>7,'home_score'=>4));
// A completed pool game keeps standings meaningful in every scenario.
$games[]=array_merge($base,array('id'=>104,'playbook_id'=>'104','time'=>'08:00','status'=>'Final','away_score'=>5,'home_score'=>2));
$bracket=array_merge($base,array('id'=>201,'playbook_id'=>'201','division'=>'12U / 13U','member_divisions'=>array('12U Open','13U Open'),'bracket_name'=>'12U / 13U Silver Bracket (combined)','pool_name'=>'','game_type_id'=>2,'championship'=>true,'round'=>'Championship','time'=>'23:30','status'=>$case==='final'?'Final':($case==='live'?'Live':'Scheduled'),'away_score'=>in_array($case,array('live','final'),true)?7:null,'home_score'=>in_array($case,array('live','final'),true)?4:null));
$fixtures=array($bracket,array_merge($bracket,array('id'=>202,'playbook_id'=>'202','championship'=>false,'round'=>'Semifinal','status'=>'Final','away_score'=>5,'home_score'=>3,'time'=>'09:00','winner_to'=>201)));
$event=array('name'=>'SWAT Fixture Invitational','event_name'=>'SWAT Fixture Invitational','slug'=>'fixture-event','start'=>$today,'end'=>$tomorrow,'start_date'=>$today,'end_date'=>$tomorrow,'divisions'=>array('12U Open','13U Open'));
$fixture_event=$event;
$payload=array('event'=>$event,'loaded_event'=>$event,'games'=>$games,'bracket_fixtures'=>$fixtures,'last_success_ts'=>time()-($case==='stale'?600:30),'last_sync'=>gmdate('Y-m-d H:i:s',time()-30));
if ($case==='unverified') { unset($payload['last_success_ts']);$payload['last_sync']=null; }
if ($case==='no-data') { $payload['games']=array();$payload['bracket_fixtures']=array(); }
if (strpos($path,'/__fixture/')===0) {
    header('Content-Type: application/json');
    if ($case==='sync-failed') { http_response_code(503);echo '{"error":"Fixture sync failure"}';exit; }
    if ($case==='loading') usleep(3000000);
    if (str_ends_with($path,'/alerts')) {echo '{}';exit;}
    if (str_ends_with($path,'/weekend')) {
        echo json_encode(array('timeline'=>$games,'team_directory'=>array(array('id'=>'winston-salem-elite-baseball-academy-black','name'=>$team,'division'=>'12U Open'))));exit;
    }
    echo json_encode($payload);exit;
}
function sanitize_title($s){return trim(preg_replace('/[^a-z0-9]+/','-',strtolower($s)),'-');}
function sanitize_key($s){return preg_replace('/[^a-z0-9_-]/','',strtolower($s));}
function sanitize_text_field($s){return strip_tags((string)$s);}
function wp_unslash($s){return $s;}
function absint($n){return abs((int)$n);}
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function esc_attr($s){return esc_html($s);}
function esc_url($s){return esc_html($s);}
function wp_json_encode($v,$f=0){return json_encode($v,$f);}
function get_query_var($key){return '';}
function wp_timezone(){return new DateTimeZone('America/New_York');}
function wp_date($format,$ts=null){return (new DateTimeImmutable('@'.($ts??time())))->setTimezone(wp_timezone())->format($format);}
function current_datetime(){return new DateTimeImmutable('now',wp_timezone());}
function current_time($format){return $format==='timestamp'?time():wp_date($format);}
function home_url($p='/'){return $p;}
function rest_url($p){return '/wp-json/'.$p;}
function selected($a,$b){if($a===$b)echo 'selected';}
function swat_os_current_event(){return $GLOBALS['fixture_event'];}
function swat_os_public_payload(){return $GLOBALS['payload'];}
function swat_os_event_by_slug($s){return $GLOBALS['fixture_event'];}
function swat_os_event_games($s){return $GLOBALS['payload']['games'];}
function swat_os_api_bracket_fixtures($s){return $GLOBALS['payload']['bracket_fixtures'];}
function swat_child_event_sync_timestamp($s){return $GLOBALS['payload']['last_sync']??null;}
function swat_os_game_is_live($g){return $g['status']==='Live';}
function swat_os_game_is_final($g){return $g['status']==='Final';}
function swat_os_division_rule_key($d){return $d;}
function swat_os_pitch_count_event_slug(...$args){return '';}
function swat_os_pitch_count_url(...$args){return '';}
function swat_os_pitch_count_for_game(...$args){return null;}
function swat_os_gameday_url(){return '/';}
function swat_os_event_directory(){return array($GLOBALS['fixture_event'],array_merge($GLOBALS['fixture_event'],array('slug'=>'second-event','event_name'=>'Second fixture event')));}
function swat_os_game_card($g){return ''; /* Actual JS renderer runs after boot. */}
function swat_os_standings_markup($g){return '';}
function swat_os_render_division_rules(...$args){echo '<p>Fixture rules placeholder. Verify actual rules on staging.</p>';}
function get_template_part($name,$slug=null,$args=array()){include $GLOBALS['theme'].'/'.$name.'.php';}
header('Content-Type: text/html; charset=utf-8');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>SWAT Game Day local fixture</title>
<style>*{box-sizing:border-box}.screen-reader-text{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%)}.fixture-note{font:12px system-ui;padding:4px 16px;background:#17171b;color:#f4f4f6}svg{width:24px;height:24px}</style>
<?php foreach(array_merge(is_file($theme.'/assets/css/shared-fonts.css') ? array('shared-fonts','shared-components') : array('child-fonts','gameday-tokens'), array('swat-gameday-hub','team-schedule','postseason','swat-gameday-compact','sponsors')) as $css) if(is_file($theme.'/assets/css/'.$css.'.css'))echo '<link rel="stylesheet" href="/assets/css/'.$css.'.css">'; ?>
<link rel="stylesheet" href="/style.css"><script src="/assets/js/appearance-boot.js"></script></head><body class="swat-gameday-mode swat-hub-ui swat-shared-ui"><div class="fixture-note">LOCAL FIXTURE — synthetic test data, not live scores</div><main id="main" class="swat-app-main">
<?php $args=array('view'=>$_GET['view']??'today');ob_start();include $theme.'/template-parts/gameday/app.php';$markup=ob_get_clean();if($case==='loading')$markup=preg_replace('#(data-swat-initial-payload>).*?(</script>)#s','$1null$2',$markup);echo $markup; ?>
</main><script>
window.SWATGameDay=<?php echo json_encode(array('gamesEndpoint'=>"/__fixture/$case/games",'publicEndpoint'=>"/__fixture/$case/games",'alertEndpoint'=>"/__fixture/$case/alerts",'myWeekendEndpoint'=>"/__fixture/$case/weekend",'gamedayUrl'=>'/','fieldDirections'=>array(),'scoreEndpoint'=>'/__fixture/disabled','helpEndpoint'=>'/__fixture/disabled')); ?>;
window.SWATGameDayHub={pitchingEnabled:true};
</script>
<?php foreach(array('swat-gameday-model','swat-gameday','swat-my-weekend','swat-field-ux') as $js) if(is_file($theme.'/assets/js/'.$js.'.js'))echo '<script src="/assets/js/'.$js.'.js"></script>'; ?>
</body></html>
