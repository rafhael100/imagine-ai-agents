// AIA-P49: execute the ORIGINAL Comercial F331-F340 module from pinned SHA.
// No copying math or changing public site behavior; CI-only cross-project parity.
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {
  buildGrowthPotential,
  planVisitsForCustomerGoal,
  GROWTH_SCENARIOS,
  GROWTH_WEEKS_PER_MONTH,
  MAX_WEEKLY_VISITS
} from '../_imagine_comercial_pin/src/lib/growth-potential-calculator.mjs';

const source=JSON.parse(execFileSync('php',['tools/check_p49_monthly_growth.php'],{
  cwd:process.cwd(),encoding:'utf8',timeout:20000,maxBuffer:2_000_000
}));
assert.equal(source.schema,'AIA_P49_MONTHLY_FUNNEL_PARITY_V1');
assert.equal(source.operational_authorization,false);
assert.equal(source.ai_calls,0);
assert.equal(source.credits_debited,0);
assert.equal(GROWTH_WEEKS_PER_MONTH,4);
assert.equal(MAX_WEEKLY_VISITS,100000);
assert.deepEqual(Object.keys(GROWTH_SCENARIOS),
  ['conservative','reference','optimistic']);
assert.deepEqual(GROWTH_SCENARIOS,{
  conservative:{leadBasisPoints:200,closeBasisPoints:1000},
  reference:{leadBasisPoints:400,closeBasisPoints:2000},
  optimistic:{leadBasisPoints:600,closeBasisPoints:3000}
});

let checks=0;
for(const row of source.weeklySamples){
  for(const scenario of Object.keys(row.scenarios)){
    const original=buildGrowthPotential({weeklyVisits:row.weeklyVisits,scenario});
    const computed=row.scenarios[scenario];
    assert.equal(computed.monthlyVisits,original.monthlyVisits);
    assert.equal(computed.leads,original.leads);
    assert.equal(computed.customers,original.customers);
    assert.equal(computed.assumptionOnly,true);
    assert.equal(computed.actualPerformanceData,false);
    checks++;
  }
}
for(const row of source.goalSamples){
  const original=planVisitsForCustomerGoal({
    monthlyCustomerGoal:row.monthlyCustomerGoal,
    scenario:row.scenario
  });
  assert.equal(row.reachable,original.reachable);
  assert.equal(row.assumptionOnly,true);
  assert.equal(row.realResultsGuaranteed,false);
  if(row.reachable){
    assert.equal(row.weeklyVisits,original.weeklyVisits);
    assert.equal(row.monthlyVisits,original.monthlyVisits);
    assert.equal(row.leads,original.leads);
    assert.equal(row.customers,original.customers);
  }else{
    assert.equal(row.weeklyCap,original.weeklyCap);
    assert.equal(row.maxIllustratedCustomers,original.maxIllustratedCustomers);
  }
  checks++;
}
assert.ok(checks>=120);
console.log('AIA_P49_CROSSREPO_COMERCIAL_MATH_PASS cases='+checks+
  ' original_f331_f340=1 four_week_month=1 three_scenarios=1 reverse_goal=1 no_ai=1 NO_GO');
