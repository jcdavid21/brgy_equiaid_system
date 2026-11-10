const fs = require('fs');
const vm = require('vm');

const context = {
  window: {},
  document: { addEventListener: function () {} },
  console: console,
  URL: URL,
  Blob: Blob,
  FormData: FormData,
  fetch: function () {},
  setTimeout: function () {},
};
vm.createContext(context);
vm.runInContext(fs.readFileSync(__dirname + '/../js/admin-typhoon-impact.js', 'utf8'), context);

const sample = `
Republic of the Philippines DEPARTMENT OF SCIENCE AND TECHNOLOGY
Philippine Atmospheric, Geophysical and Astronomical Services Administration (PAGASA)
TROPICAL CYCLONE PRELIMINARY REPORT
Tropical Storm MAYMAY KUJIRA (2614) 04 to 06 August 2026
Date Published | 31 August 2026
Summary of Meteorological History
Developed into a tropical cyclone 0000 UTC, 04 August 2026
Weakened into a remnant low or transitioned into a post-tropical low 1200 UTC, 06 August 2026
Peak intensity (lifetime2) 45 kt (85 km/h), 994 hPa, Tropical Storm 0000 UTC, 05 August 2026
Entered the PAR region (as tropical cyclone) Not applicable (developed within the PAR region)
Exited the PAR region (as tropical cyclone) Not applicable (weakened into a remnant low while inside the PAR)
Observed landfall/s in the Philippines Santa Catalina, Ilocos Sur: 19300 UTC, 05 August 2026
Tropical Cyclone Bulletins: o First issuance: 11:00 PM, 04 August 2026 o Last issuance: 5:00 AM, 07 August 2026 o Total issued: 17
Tropical Cyclone Warnings for Shipping:
Highest level of wind signal hoisted: 2
`;

const parsed = context.window.PagasaReportParser.parse(
  sample,
  'https://pubfiles.pagasa.dost.gov.ph/PAGASA_Prelim_2026_MAYMAY.pdf',
  'PAGASA_Prelim_2026_MAYMAY.pdf'
);

if (parsed.pagasa_name !== 'MAYMAY') throw new Error('Local name parsing failed.');
if (parsed.international_name !== 'KUJIRA') throw new Error('International name parsing failed.');
if (parsed.classification !== 'Tropical Storm') throw new Error('Classification parsing failed.');
if (parsed.peak_wind_kph !== 85 || parsed.minimum_pressure_hpa !== 994) throw new Error('Intensity parsing failed.');
if (parsed.landfall_at_utc !== '2026-08-05T19:30:00Z') throw new Error('Landfall time normalization failed.');
if (parsed.bulletins_issued !== 17 || parsed.highest_tcws !== 2) throw new Error('Warning summary parsing failed.');

console.log('PAGASA report parser tests passed.');
