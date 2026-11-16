<?php
declare(strict_types=1);

require_once __DIR__.'/../src/Automation/UnifiedGrowthProjectionService.php';
use ImagineAI\Automation\UnifiedGrowthProjectionService;

/**
 * P50 standalone CLI for a no-network, no-PII preview:
 * php tools/preview_growth_projection.php n8n_weekly 120 1000 2000
 * php tools/preview_growth_projection.php comercial_monthly 120 reference
 * No server, app account, export or event persistence is involved.
 */
$usage="Usage:\n  php tools/preview_growth_projection.php n8n_weekly <visits> <lead_rate_bp> <client_rate_bp>\n  php tools/preview_growth_projection.php comercial_monthly <visits> <conservative|reference|optimistic>\n";
if(PHP_SAPI!=='cli' || $argc<4 ||$argc>5
    ||!in_array($argv[1],['n8n_weekly','comercial_monthly'],true)){
    fwrite(STDERR,$usage);
    exit(2);
}
$mode=$argv[1];
if(($mode==='n8n_weekly'&&$argc!==5)
    ||($mode==='comercial_monthly'&&$argc!==4)){
    fwrite(STDERR,$usage);
    exit(2);
}
$parseInt=static function(string $s):int{
    if(preg_match('/\A(?:0|[1-9][0-9]{0,8})\z/D',$s)!==1){
        throw new InvalidArgumentException('ONLY_BOUNDED_DECIMAL_INTEGERS');
    }
    return (int)$s;
};
try{
    $command=[
        'schema'=>'AIA_GROWTH_PROJECTION_COMMAND_V1',
        'mode'=>$mode,
        'tenant_ref'=>'SYNTHETIC_TENANT_DEMO',
        'request_ref'=>'SYNTHETIC_CLI_PREVIEW',
        'weekly_visits'=>$parseInt($argv[2]),
    ];
    if($mode==='n8n_weekly'){
        $command['lead_rate_bp']=$parseInt($argv[3]);
        $command['client_rate_bp']=$parseInt($argv[4]);
    }else{
        $command['scenario']=$argv[3];
    }
    echo json_encode((new UnifiedGrowthProjectionService())->project($command),
        JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
}catch(InvalidArgumentException){
    fwrite(STDERR,"AIA_P50_PREVIEW_INPUT_DENIED\n");
    exit(2);
}
