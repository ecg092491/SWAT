<?php
/** Guarded, synthetic home fixture using the actual child header and front-page. */
if ( ! in_array( PHP_SAPI, array( 'cli', 'cli-server' ), true ) || getenv('SWAT_FIXTURE_ENABLED') !== '1' ) { http_response_code(404); exit; }
error_reporting(E_ALL);
ini_set('display_errors','1');
define('ABSPATH',__DIR__.'/');
define('SWAT_OS_DIR',__DIR__.'/fixture-parent');
$theme=realpath(getenv('SWAT_FIXTURE_THEME') ?: __DIR__.'/../..');
$fixture_case=$_GET['case']??getenv('SWAT_HOME_CASE')?:'open';
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
if ($path==='/style.css' || strpos($path,'/assets/')===0) {
 $file=realpath($theme.$path);
 if (!$file || strpos($file,$theme.'/')!==0) { http_response_code(404);exit; }
 header('Content-Type: '.(['css'=>'text/css','js'=>'text/javascript','ttf'=>'font/ttf','woff2'=>'font/woff2'][pathinfo($file,PATHINFO_EXTENSION)]??'application/octet-stream'));readfile($file);exit;
}
function __($s,$domain=''){return $s;} function esc_html__($s,$d=''){return esc_html($s);}function esc_html_e($s,$d=''){echo esc_html($s);}function esc_attr_e($s,$d=''){echo esc_attr($s);}
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}function esc_attr($s){return esc_html($s);}function esc_url($s){return esc_html($s);}
function home_url($p='/'){return '/site'.$p;}function swat_os_gameday_url(){return home_url('/gameday/');}function swat_os_is_gameday_request(){return false;}
function wp_timezone(){return new DateTimeZone('America/New_York');}function wp_date($f,$ts){return (new DateTimeImmutable('@'.$ts))->setTimezone(wp_timezone())->format($f);}
function language_attributes(){echo 'lang="en"';}function bloginfo($s){echo 'UTF-8';}function get_bloginfo($s){return 'SWAT Tournaments';}
function swat_os_logo_url(){return 'data:image/svg+xml,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="40"><text y="30" fill="gold" font-size="32">SWAT</text></svg>');}
function body_class(){echo 'class="home swat-os swat-shared-ui"';}function wp_body_open(){}function has_nav_menu($s){return false;}
function wp_head(){echo '<title>SWAT Home — synthetic fixture</title>';foreach(array_merge(is_file($GLOBALS['theme'].'/assets/css/shared-fonts.css') ? ['shared-fonts','shared-components'] : ['child-fonts'], ['home','team-finder']) as $name)echo '<link rel="stylesheet" href="/assets/css/'.$name.'.css">';echo '<link rel="stylesheet" href="/style.css"><script src="/assets/js/appearance-boot.js"></script>';}
function get_header(){include $GLOBALS['theme'].'/header.php';echo '<p style="text-align:center">TEST FIXTURE — synthetic events, not live availability</p>';}
function get_footer(){echo '</main><footer>Fixture footer — verify inherited footer on staging.</footer><script src="/assets/js/home.js"></script><script src="/assets/js/shared-ui.js"></script></body></html>';}
function swat_child_render_team_finder($args){echo '<div class="swat-finder"><label>Fixture team search <input aria-label="Fixture team search"></label></div>';}
function swat_child_home_status_badge($state){return '<span class="swat-reg-badge swat-reg-badge--'.esc_attr(str_replace('_','-',$state['status'])).'">'.esc_html($state['label']).'</span>';}
function swat_child_status_badge($state){return swat_child_home_status_badge($state);}
function swat_child_home_action_link($a){echo '<a class="swh-btn '.($a['primary']?'swh-btn--primary':'swh-btn--secondary').'" href="'.esc_url($a['url']).'">'.esc_html($a['label']).'</a>';}
function swat_child_home_sections(){
 $case=$GLOBALS['fixture_case'];$status=in_array($case,['open','limited','sold_out','closed'],true)?$case:'open';$today=(new DateTimeImmutable('now',wp_timezone()))->format('Y-m-d');$start=(new DateTimeImmutable('tomorrow',wp_timezone()))->format('Y-m-d');
 $a=[['key'=>'details','primary'=>false,'label'=>'Details','url'=>home_url('/tournaments/fixture-event/')]];
 if(in_array($status,['open','limited']))array_unshift($a,['key'=>'register','primary'=>true,'label'=>'Register','url'=>'https://play.swatourneys.com/']);
 $e=['start'=>$start,'end'=>$start,'dates'=>'Synthetic event date','title'=>'SWAT <script>alert(1)</script> & Invitational','is_live'=>false,'has_schedule'=>false,'show_badge'=>true,'reg_state'=>['status'=>$status,'label'=>ucwords(str_replace('_',' ',$status))],'divisions'=>'12U / 13U','note'=>'','deadline_label'=>'','actions'=>$a,'detail_url'=>home_url('/tournaments/fixture-event/')];
 $weekend=[];if($case==='live'){$e['start']=$today;$e['is_live']=true;$weekend=[$e];}
 return ['weekend'=>$weekend,'weekend_label'=>'Synthetic fixture weekend','upcoming'=>$case==='empty'?[]:[$e,$e,$e],'upcoming_more'=>0,'previous'=>[],'any_pending'=>false];
}
include $theme.'/front-page.php';
