const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

// Exercise overlapping dashboard requests: the latest selection must win.
const source = fs.readFileSync(__dirname + '/../js/admin-typhoon-impact.js', 'utf8');
const start = source.indexOf('  var eventLoadGeneration = 0;');
const end = source.indexOf('  function renderEventBanner(', start);
const requests = new Map();
const rendered = [];
const maps = [];
const context = {
  console,
  currentEventId: null,
  currentEvent: null,
  allImpacts: [],
  el: () => null,
  api: (action, params) => new Promise(resolve => requests.set(`${params.event_id}:${action}`, resolve)),
  renderEventBanner: event => rendered.push(event.event_id),
  loadMapData: id => maps.push(id),
};
for (const name of ['renderKPIs', 'renderImpactBars', 'renderRiskBreakdown', 'renderWelfareStatus', 'renderBudget', 'renderTable', 'populateZoneFilter', 'renderLegend']) context[name] = () => {};
vm.createContext(context);
vm.runInContext(source.slice(start, end), context);
function finish(id) {
  requests.get(`${id}:summary`)({event: {event_id: id}, summary: {risk_breakdown: {}, budget: {}}});
  requests.get(`${id}:impact_list`)({impacts: [{street_id: id}]});
}
(async () => {
  const earlier = context.loadEventData(1);
  const latest = context.loadEventData(2);
  finish(2);
  await latest;
  finish(1);
  await earlier;
  assert.deepEqual(rendered, [2]);
  assert.deepEqual(maps, [2]);
  assert.equal(context.currentEventId, 2);
  assert.equal(context.allImpacts[0].street_id, 2);
  console.log('Typhoon selection regression passed: stale responses cannot overwrite the selected event.');
})().catch(error => { console.error(error); process.exitCode = 1; });
