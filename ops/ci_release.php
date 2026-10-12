<?php
// Private CLI orchestration. Hosting configuration and initial bootstrap are required.
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
function ci_command(array $command,array $env):void {
    $process=proc_open($command,[0=>['file','/dev/null','r'],1=>STDOUT,2=>STDERR],$pipes,null,$env);
    if(!is_resource($process)||proc_close($process)!==0)throw new RuntimeException('Release step failed; inspect the preceding preflight or smoke error.');
}
try{
    $input=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
    if(!is_array($input))throw new RuntimeException('Invalid deployment request.');
    foreach(['account_root','php_bin','smoke_user','smoke_password']as$key)if(!isset($input[$key])||!is_string($input[$key])||$input[$key]==='')throw new RuntimeException('Missing deployment setting: '.$key);
    $account=realpath($input['account_root']);$php=$input['php_bin'];
    if(!$account||$account==='/'||in_array('public_html',explode('/',$account),true))throw new RuntimeException('Use the private hosting account root.');
    if(!str_starts_with($php,'/')||!is_executable($php))throw new RuntimeException('PHP CLI path is invalid.');
    $lock=fopen($account.'/.tabeeb-ci-deploy-lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Another hosting deployment is active.');
    // Refuse automated first-install cutover: accounts, SMTP, mappings and rollback
    // must be verified once before AUTO_DEPLOY_ENABLED is enabled.
    foreach(['staging','production']as$target){
        $root=$account.'/tabeeb-'.$target;$active=realpath($root.'/current');
        if(!is_file($root.'/shared/.env')||!is_link($root.'/current')||!$active||!str_starts_with($active,$root.'/releases/'))throw new RuntimeException('Finish initial '.$target.' setup and verify its current release first.');
    }
    $env=getenv();unset($env['APP_ENV_FILE'],$env['APP_ENV'],$env['SMOKE_USER'],$env['SMOKE_PASSWORD']);$env['PHP_BIN']=$php;
    foreach(['staging','production']as$target){
        $root=$account.'/tabeeb-'.$target;$stageEnv=$env;
        if($target==='staging'){$stageEnv['SMOKE_USER']=$input['smoke_user'];$stageEnv['SMOKE_PASSWORD']=$input['smoke_password'];}
        ci_command(['bash',__DIR__.'/release.sh','prepare',$root,$target],$stageEnv);
        $release=trim((string)file_get_contents($root.'/prepared'));
        if(!preg_match('/^[A-Za-z0-9_-]+$/',$release))throw new RuntimeException('Invalid prepared release identity.');
        ci_command(['bash',__DIR__.'/release.sh','activate',$root,$target,$release],$stageEnv);
    }
    echo "Both environment releases activated and HTTP-verified.\n";
}catch(Throwable $error){fwrite(STDERR,'CI release stopped: '.$error->getMessage()."\n");exit(1);}
