<?php
declare(strict_types=1);

/** F232 — offline-only fail-closed synthetic scope regression. */
use ImagineAI\MockBridge\{MockCrmReadRequest, MockSyntheticScopePreflight};
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class) use ($root): void {
    $prefix='ImagineAI\\';
    if (str_starts_with($class,$prefix)) {
        require $root.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    }
});
function f232Assert(bool $condition): void {
    if (!$condition) throw new RuntimeException('F232_SCOPE_DENIED');
}
function f232Wire(array $changes=[]): string {
    return MockCrmReadRequest::encodeCanonicalJson(array_replace([
        'contract'=>'SYNTHETIC_CRM_AIA_READ_V1',
        'request_ref'=>'SYNTHETIC_F232_REQUEST',
        'operation'=>'crm.lead.read_minimal',
        'tenant_ref'=>'SYNTHETIC_TENANT_A',
        'principal_ref'=>'SYNTHETIC_PRINCIPAL_A',
        'resource_ref'=>'SYNTHETIC_LEAD_A',
        'purpose_ref'=>'SYNTHETIC_PURPOSE_A',
        'fields'=>['ref','stage'],
    ],$changes));
}
try {
    $request=f232Wire();
    $scope=[
        'tenant_ref'=>'SYNTHETIC_TENANT_A',
        'principal_ref'=>'SYNTHETIC_PRINCIPAL_A',
        'resource_ref'=>'SYNTHETIC_LEAD_A',
        'purpose_ref'=>'SYNTHETIC_PURPOSE_A',
        'fields'=>['ref','stage'],
    ];
    f232Assert(MockSyntheticScopePreflight::matches($request,$scope));
    f232Assert(MockSyntheticScopePreflight::matches(f232Wire(['fields'=>['ref']]),$scope));
    $denied=[
        [f232Wire(['tenant_ref'=>'SYNTHETIC_TENANT_B']),$scope],
        [f232Wire(['principal_ref'=>'SYNTHETIC_PRINCIPAL_B']),$scope],
        [f232Wire(['resource_ref'=>'SYNTHETIC_LEAD_B']),$scope],
        [f232Wire(['purpose_ref'=>'SYNTHETIC_PURPOSE_B']),$scope],
        [$request,array_replace($scope,['fields'=>['ref']])],
        [$request,array_replace($scope,['tenant_ref'=>'SYNTHETIC_TENANT_B'])],
        [$request,array_replace($scope,['principal_ref'=>'SYNTHETIC_PRINCIPAL_B'])],
        [$request,array_replace($scope,['resource_ref'=>'SYNTHETIC_LEAD_B'])],
        [$request,array_replace($scope,['purpose_ref'=>'SYNTHETIC_PURPOSE_B'])],
        [$request,array_replace($scope,['fields'=>['ref','ref']])],
        [$request,array_replace($scope,['fields'=>['ref','email']])],
        [$request,array_replace($scope,['fields'=>[]])],
        [$request,array_replace($scope,['tenant_ref'=>123])],
        [$request,array_replace($scope,['tenant_ref'=>'TENANT_A'])],
        [$request,array_merge($scope,['allow'=>true])],
        [$request,array_reverse($scope,true)],
        [$request.' ',$scope],
        ['',$scope],
        [str_repeat('X',1025),$scope],
        [substr($request,0,-1).',"is_admin":true}',$scope],
    ];
    foreach ($denied as [$body,$trusted]) {
        f232Assert(!MockSyntheticScopePreflight::matches($body,$trusted));
    }
    f232Assert(count($denied)===20);
    echo "F232_SCOPE_PREFLIGHT_PASS positive=2 negative=20 operational_decision=NO_GO real_authorization=false external_calls=0\n";
} catch (Throwable) {
    fwrite(STDERR,"F232_SCOPE_PREFLIGHT_DENIED operational_decision=NO_GO\n");
    exit(1);
}
