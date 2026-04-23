'use strict';

(function () {
  function waitForChart(cb) {
    if (typeof Chart !== 'undefined') { cb(); return; }
    let tries = 0;
    const iv = setInterval(() => {
      tries++;
      if (typeof Chart !== 'undefined') { clearInterval(iv); cb(); }
      else if (tries > 40) { clearInterval(iv); } // ~4s cap
    }, 100);
  }

  const palette = {
    ink:      '#06141b',
    muted:    '#30414f',
    line:     'rgba(37, 55, 69, 0.14)',
    gold:     '#d9b583',
    goldDeep: '#b5935a',
    navy:     '#11212d',
    navySoft: '#253745',
    cream:    '#f5f3ef',
    green:    '#4da664',
    red:      '#c8372d',
  };

  function gradient(ctx, from, to) {
    const chartArea = ctx.chart.chartArea;
    if (!chartArea) return from;
    const g = ctx.chart.ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
    g.addColorStop(0, from);
    g.addColorStop(1, to);
    return g;
  }

  function makeLineConfig({ labels, data, color = palette.navy, fillFrom = 'rgba(17,33,45,0.22)', fillTo = 'rgba(17,33,45,0)' }) {
    return {
      type: 'line',
      data: {
        labels,
        datasets: [{
          data,
          borderColor: color,
          borderWidth: 2,
          tension: 0.35,
          pointRadius: 0,
          pointHoverRadius: 4,
          pointHoverBackgroundColor: color,
          fill: true,
          backgroundColor: (ctx) => gradient(ctx, fillFrom, fillTo),
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { display: false }, tooltip: tooltipStyle() },
        scales: {
          x: {
            grid: { display: false },
            ticks: { color: palette.muted, font: { size: 10 }, maxRotation: 0, autoSkipPadding: 12 },
            border: { display: false },
          },
          y: {
            grid: { color: palette.line, drawBorder: false },
            ticks: { color: palette.muted, font: { size: 10 } },
            border: { display: false },
          },
        },
      },
    };
  }

  function makeBarConfig({ labels, data, color = palette.navy, horizontal = false }) {
    return {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          data,
          backgroundColor: color,
          borderRadius: 6,
          borderSkipped: false,
          maxBarThickness: horizontal ? 18 : 28,
        }],
      },
      options: {
        indexAxis: horizontal ? 'y' : 'x',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: tooltipStyle() },
        scales: {
          x: {
            grid: { color: palette.line, drawBorder: false, display: !horizontal },
            ticks: { color: palette.muted, font: { size: 10 } },
            border: { display: false },
          },
          y: {
            grid: { display: false },
            ticks: { color: palette.muted, font: { size: 10 } },
            border: { display: false },
          },
        },
      },
    };
  }

  function makeDonutConfig({ labels, data, colors }) {
    return {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{ data, backgroundColor: colors, borderColor: '#fefcff', borderWidth: 2 }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '62%',
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              color: palette.muted,
              font: { size: 11 },
              boxWidth: 10,
              boxHeight: 10,
              padding: 10,
              usePointStyle: true,
            },
          },
          tooltip: tooltipStyle(),
        },
      },
    };
  }

  function tooltipStyle() {
    return {
      backgroundColor: palette.navy,
      titleColor: '#fefcff',
      bodyColor: 'rgba(254,252,255,0.92)',
      borderColor: 'rgba(245,243,239,0.12)',
      borderWidth: 1,
      padding: 10,
      cornerRadius: 10,
      displayColors: false,
      titleFont: { size: 11, weight: '700' },
      bodyFont: { size: 11, weight: '600' },
    };
  }

  function init() {
    const d = window.__insightsData || {};
    const labels = d.labels || [];

    const charts = [];

    const $ = (id) => document.getElementById(id);

    if ($('chart-views')) charts.push(new Chart($('chart-views'), makeLineConfig({
      labels, data: d.views || [], color: palette.navy,
      fillFrom: 'rgba(17,33,45,0.2)', fillTo: 'rgba(17,33,45,0)',
    })));

    if ($('chart-apps')) charts.push(new Chart($('chart-apps'), makeLineConfig({
      labels, data: d.apps || [], color: palette.goldDeep,
      fillFrom: 'rgba(217,181,131,0.32)', fillTo: 'rgba(217,181,131,0)',
    })));

    if ($('chart-saves')) charts.push(new Chart($('chart-saves'), makeLineConfig({
      labels, data: d.saves || [], color: palette.green,
      fillFrom: 'rgba(77,166,100,0.22)', fillTo: 'rgba(77,166,100,0)',
    })));

    if ($('chart-ctr')) {
      const ctr = (d.views || []).map((v, i) => {
        const a = (d.apps || [])[i] || 0;
        return v > 0 ? Number(((a / v) * 100).toFixed(2)) : 0;
      });
      charts.push(new Chart($('chart-ctr'), makeLineConfig({
        labels, data: ctr, color: palette.red,
        fillFrom: 'rgba(200,55,45,0.2)', fillTo: 'rgba(200,55,45,0)',
      })));
    }

    if ($('chart-gender')) new Chart($('chart-gender'), makeDonutConfig({
      labels: ['Kadın', 'Erkek', 'Belirtmek istemiyor'],
      data: [46, 48, 6],
      colors: [palette.gold, palette.navy, palette.navySoft],
    }));

    if ($('chart-age')) new Chart($('chart-age'), makeBarConfig({
      labels: ['18–24', '25–29', '30–34', '35–40', '40+'],
      data: [14, 38, 26, 15, 7],
      color: palette.navy,
    }));

    if ($('chart-education')) new Chart($('chart-education'), makeDonutConfig({
      labels: ['Lise', 'Lisans', 'Y. Lisans', 'Doktora'],
      data: [8, 62, 24, 6],
      colors: ['#c5c8c4', palette.navy, palette.gold, palette.goldDeep],
    }));

    if ($('chart-experience')) new Chart($('chart-experience'), makeDonutConfig({
      labels: ['Junior', 'Mid', 'Senior', 'Lead'],
      data: [34, 42, 18, 6],
      colors: [palette.gold, palette.navy, palette.goldDeep, palette.navySoft],
    }));

    if ($('chart-universities')) new Chart($('chart-universities'), makeBarConfig({
      labels: ['Boğaziçi', 'ODTÜ', 'İTÜ', 'Sabancı', 'Koç', 'Bilkent', 'YTÜ', 'Ege', 'Hacettepe', 'Ankara'],
      data: [22, 19, 17, 13, 12, 10, 8, 6, 5, 4],
      color: palette.navy,
      horizontal: true,
    }));

    if ($('chart-cities')) new Chart($('chart-cities'), makeBarConfig({
      labels: ['İstanbul', 'Ankara', 'İzmir', 'Bursa', 'Antalya', 'Kocaeli', 'Konya', 'Adana', 'Gaziantep', 'Eskişehir'],
      data: [48, 18, 11, 6, 5, 4, 3, 2, 2, 1],
      color: palette.goldDeep,
      horizontal: true,
    }));

    if ($('chart-source')) new Chart($('chart-source'), makeDonutConfig({
      labels: ['Arama', 'Ana sayfa', 'Profil', 'Direkt', 'Paylaşım'],
      data: [42, 26, 14, 12, 6],
      colors: [palette.navy, palette.gold, palette.navySoft, palette.goldDeep, '#9ba8ab'],
    }));

    if ($('chart-device')) new Chart($('chart-device'), makeDonutConfig({
      labels: ['Mobil', 'Masaüstü', 'Tablet'],
      data: [58, 38, 4],
      colors: [palette.navy, palette.gold, palette.navySoft],
    }));

    if ($('chart-trend')) {
      const trendLabels = [];
      const trendData = [];
      for (let i = 11; i >= 0; i--) {
        const d = new Date();
        d.setDate(d.getDate() - i * 7);
        trendLabels.push(d.toLocaleDateString('tr-TR', { day: 'numeric', month: 'short' }));
        const base = 62 + Math.sin(i / 2) * 10 + (11 - i) * 1.5;
        trendData.push(Math.round(base));
      }
      new Chart($('chart-trend'), makeLineConfig({
        labels: trendLabels,
        data: trendData,
        color: palette.goldDeep,
        fillFrom: 'rgba(217,181,131,0.3)',
        fillTo: 'rgba(217,181,131,0)',
      }));
    }

    // Hourly heatmap (Chart.js matrix plugin)
    if ($('chart-hourly')) {
      const days = ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'];
      const hours = Array.from({ length: 24 }, (_, h) => h);
      const data = [];
      let maxV = 0;
      for (let d = 0; d < 7; d++) {
        for (let h = 0; h < 24; h++) {
          // Mock: workdays (Mon–Fri 09–19) peak, weekends quieter
          const workday = d < 5;
          const workHour = h >= 9 && h <= 19;
          let base = 2;
          if (workday && workHour) base = 18 + Math.round(10 * Math.sin((h - 10) / 3));
          else if (!workday && h >= 11 && h <= 22) base = 9 + Math.round(4 * Math.sin((h - 14) / 4));
          const v = Math.max(0, base + Math.round((Math.random() - 0.5) * 6));
          if (v > maxV) maxV = v;
          data.push({ x: h, y: d, v });
        }
      }

      new Chart($('chart-hourly'), {
        type: 'matrix',
        data: {
          datasets: [{
            label: 'Görüntülenme',
            data,
            backgroundColor(ctx) {
              const v = ctx.raw && ctx.raw.v ? ctx.raw.v : 0;
              const t = maxV ? v / maxV : 0;
              // Navy → gold blend by intensity
              const r = Math.round(17 + (217 - 17) * t);
              const g = Math.round(33 + (181 - 33) * t);
              const b = Math.round(45 + (131 - 45) * t);
              return `rgba(${r}, ${g}, ${b}, ${0.25 + 0.75 * t})`;
            },
            borderColor: 'rgba(255,255,255,0.4)',
            borderWidth: 1,
            width: ({ chart }) => {
              const a = chart.chartArea;
              return a ? (a.right - a.left) / 24 - 1 : 12;
            },
            height: ({ chart }) => {
              const a = chart.chartArea;
              return a ? (a.bottom - a.top) / 7 - 1 : 16;
            },
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: Object.assign(tooltipStyle(), {
              callbacks: {
                title: (items) => `${days[items[0].raw.y]} · ${String(items[0].raw.x).padStart(2, '0')}:00`,
                label: (item) => `${item.raw.v} görüntülenme`,
              },
            }),
          },
          scales: {
            x: {
              type: 'linear',
              position: 'bottom',
              offset: true,
              min: -0.5,
              max: 23.5,
              ticks: {
                stepSize: 3,
                color: palette.muted,
                font: { size: 10 },
                callback: (v) => String(v).padStart(2, '0') + ':00',
              },
              grid: { display: false },
              border: { display: false },
            },
            y: {
              type: 'linear',
              offset: true,
              reverse: true,
              min: -0.5,
              max: 6.5,
              ticks: {
                stepSize: 1,
                color: palette.muted,
                font: { size: 10 },
                callback: (v) => days[v] || '',
              },
              grid: { display: false },
              border: { display: false },
            },
          },
        },
      });
    }

    // Range selector (cosmetic for now — real backend filtering wires in later)
    document.querySelectorAll('.in-range-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.in-range-btn').forEach((b) => b.classList.remove('is-active'));
        btn.classList.add('is-active');
      });
    });

    initMap();
    initWordcloud();
  }

  function initMap() {
    const el = document.getElementById('tr-map');
    if (!el || typeof L === 'undefined') return;

    // Mock country-level demand (ISO 3166-1 alpha-3) — replaced with real data once tracking lands.
    const countryData = {
      TUR: 72, GBR: 14, DEU: 9, NLD: 6, AZE: 6,
      USA: 4, FRA: 4, BGR: 4, GRC: 3, IRN: 3, ARE: 5,
      SAU: 4, QAT: 3, CYP: 2, RUS: 2, UKR: 2, POL: 3,
      ESP: 2, ITA: 3, CHE: 2, AUT: 2, SWE: 2, DNK: 1,
      NOR: 1, BEL: 2, CAN: 2, AUS: 2, IRL: 1, PRT: 1,
      IND: 2, JPN: 1, KOR: 1, SGP: 1, BRA: 1, MEX: 1,
    };

    // ISO-3 → ISO-2 (for matching admin-1 features where Natural Earth uses iso_a2)
    const iso3to2 = {
      TUR:'TR', GBR:'GB', DEU:'DE', NLD:'NL', AZE:'AZ',
      USA:'US', FRA:'FR', BGR:'BG', GRC:'GR', IRN:'IR',
      ARE:'AE', SAU:'SA', QAT:'QA', CYP:'CY', RUS:'RU',
      UKR:'UA', POL:'PL', ESP:'ES', ITA:'IT', CHE:'CH',
      AUT:'AT', SWE:'SE', DNK:'DK', NOR:'NO', BEL:'BE',
      CAN:'CA', AUS:'AU', IRL:'IE', PRT:'PT', IND:'IN',
      JPN:'JP', KOR:'KR', SGP:'SG', BRA:'BR', MEX:'MX',
    };

    // Mock province-level data for Turkey (keys match province names in the GeoJSON source).
    const provinceData = {
      TUR: {
        'Istanbul': 48, 'İstanbul': 48,
        'Ankara': 18,
        'Izmir': 11, 'İzmir': 11,
        'Bursa': 6, 'Antalya': 5,
        'Kocaeli': 4, 'Konya': 3, 'Adana': 2, 'Gaziantep': 2,
        'Eskisehir': 1, 'Eskişehir': 1,
        'Kayseri': 1, 'Samsun': 1, 'Mersin': 1, 'Trabzon': 1,
        'Diyarbakir': 1, 'Diyarbakır': 1,
      },
    };

    // Per-country admin-1 GeoJSON URLs — used in priority over the generic NE 50m file
    // for countries that NE 50m doesn't cover (Turkey, most of EU).
    const perCountryAdmin = {
      TUR: 'https://cdn.jsdelivr.net/gh/codeforgermany/click_that_hood@main/public/data/turkey.geojson',
      DEU: 'https://cdn.jsdelivr.net/gh/codeforgermany/click_that_hood@main/public/data/germany.geojson',
      FRA: 'https://cdn.jsdelivr.net/gh/codeforgermany/click_that_hood@main/public/data/france-regions.geojson',
      ESP: 'https://cdn.jsdelivr.net/gh/codeforgermany/click_that_hood@main/public/data/spain-provinces.geojson',
      ITA: 'https://cdn.jsdelivr.net/gh/codeforgermany/click_that_hood@main/public/data/italy-regions.geojson',
      GBR: 'https://cdn.jsdelivr.net/gh/codeforgermany/click_that_hood@main/public/data/united-kingdom.geojson',
      POL: 'https://cdn.jsdelivr.net/gh/codeforgermany/click_that_hood@main/public/data/poland.geojson',
      CHE: 'https://cdn.jsdelivr.net/gh/codeforgermany/click_that_hood@main/public/data/switzerland.geojson',
      PRT: 'https://cdn.jsdelivr.net/gh/codeforgermany/click_that_hood@main/public/data/portugal.geojson',
      HUN: 'https://cdn.jsdelivr.net/gh/codeforgermany/click_that_hood@main/public/data/hungary.geojson',
      ROU: 'https://cdn.jsdelivr.net/gh/codeforgermany/click_that_hood@main/public/data/romania.geojson',
      JPN: 'https://cdn.jsdelivr.net/gh/codeforgermany/click_that_hood@main/public/data/japan.geojson',
      MEX: 'https://cdn.jsdelivr.net/gh/codeforgermany/click_that_hood@main/public/data/mexico.geojson',
    };
    const perCountryCache = {};

    function colorFor(v) {
      if (!v)      return '#eeebe5';
      if (v < 3)   return '#cfddeb';
      if (v < 8)   return '#93b7d4';
      if (v < 16)  return '#5688af';
      if (v < 40)  return '#2e5e84';
      return '#0f3152';
    }

    const map = L.map(el, {
      center: [30, 15],
      zoom: 2,
      minZoom: 2,
      maxZoom: 10,
      zoomControl: true,
      scrollWheelZoom: false,
      worldCopyJump: true,
      attributionControl: true,
      maxBounds: [[-85, -180], [85, 180]],
      maxBoundsViscosity: 1.0,
    });
    map.attributionControl.setPrefix('');
    map.attributionControl.addAttribution('&copy; <a href="https://www.naturalearthdata.com/" target="_blank" rel="noopener">Natural Earth</a>');

    const resetBtn = document.getElementById('map-reset');
    const loader = document.getElementById('map-loader');
    let worldLayer = null;
    let adminLayer = null;
    let adminCache = null;

    const showLoader = (on) => { if (loader) loader.hidden = !on; };

    const worldView = () => {
      map.setView([30, 15], 2);
      if (adminLayer) { adminLayer.remove(); adminLayer = null; }
      if (resetBtn) resetBtn.hidden = true;
    };

    if (resetBtn) resetBtn.addEventListener('click', worldView);

    function loadAdminOnce() {
      if (adminCache) return Promise.resolve(adminCache);
      showLoader(true);
      return fetch('https://cdn.jsdelivr.net/gh/nvkelso/natural-earth-vector@master/geojson/ne_50m_admin_1_states_provinces.geojson')
        .then((r) => r.json())
        .then((data) => { adminCache = data; return data; })
        .finally(() => showLoader(false));
    }

    function drillInto(countryFeature) {
      if (!countryFeature) return;
      const iso3 = countryFeature.id;
      const iso2 = iso3to2[iso3];
      const countryName = (countryFeature.properties && countryFeature.properties.name) || iso3;
      const dataForCountry = provinceData[iso3] || {};

      if (adminLayer) { adminLayer.remove(); adminLayer = null; }
      if (resetBtn) resetBtn.hidden = false;

      const zoomOpts = { padding: [14, 14], maxZoom: 8, duration: 0.7 };

      const renderProvinces = (featureCollection) => {
        adminLayer = L.geoJSON(featureCollection, {
          style: (f) => {
            const pn = (f.properties || {}).name || '';
            return {
              fillColor: colorFor(dataForCountry[pn] || 0),
              color: '#fefcff',
              weight: 0.6,
              fillOpacity: 0.96,
            };
          },
          onEachFeature: (feature, layer) => {
            const p = feature.properties || {};
            const pn = p.name || p.name_en || '';
            const localName = p.name_tr || p.name_local || pn;
            const v = dataForCountry[pn] || 0;
            layer.bindTooltip(
              `<span class="in-map-tt"><strong>${localName}</strong><br>${v ? v + '% başvuru' : 'veri toplanıyor'}</span>`,
              { direction: 'auto', sticky: true, opacity: 1, className: 'in-map-tooltip' }
            );
            layer.on('mouseover', (e) => {
              e.target.setStyle({ weight: 1.7, color: '#11212d', fillOpacity: 1 });
              e.target.bringToFront();
            });
            layer.on('mouseout', () => adminLayer.resetStyle(layer));
          },
        }).addTo(map);
        map.flyToBounds(adminLayer.getBounds(), zoomOpts);
      };

      const renderFallback = () => {
        adminLayer = L.geoJSON(countryFeature, {
          style: () => ({
            fillColor: colorFor(countryData[iso3] || 0),
            color: '#fefcff',
            weight: 0.8,
            fillOpacity: 0.96,
          }),
          onEachFeature: (feat, layer) => {
            const v = countryData[iso3] || 0;
            layer.bindTooltip(
              `<span class="in-map-tt"><strong>${countryName}</strong><br>${v ? v + '% başvuru' : 'veri toplanıyor'}</span>`,
              { direction: 'auto', sticky: true, opacity: 1, className: 'in-map-tooltip' }
            );
          },
        }).addTo(map);
        map.flyToBounds(adminLayer.getBounds(), zoomOpts);
      };

      showLoader(true);

      const perUrl = perCountryAdmin[iso3];
      const loadPer = perUrl
        ? (perCountryCache[iso3]
            ? Promise.resolve(perCountryCache[iso3])
            : fetch(perUrl).then((r) => r.ok ? r.json() : null).then((d) => { if (d) perCountryCache[iso3] = d; return d; }))
        : Promise.resolve(null);

      loadPer.then((perData) => {
        if (perData && perData.features && perData.features.length) {
          renderProvinces(perData);
          showLoader(false);
          return;
        }

        // Fall back to NE 50m generic dataset (covers USA, Russia, China, India, etc.)
        return loadAdminOnce().then((all) => {
          const feats = (all && all.features) ? all.features.filter((f) => {
            const p = f.properties || {};
            return (iso2 && (p.iso_a2 === iso2 || (p.iso_3166_2 && String(p.iso_3166_2).startsWith(iso2 + '-'))))
                || (p.adm0_a3 === iso3)
                || (p.sov_a3 === iso3)
                || (p.gu_a3 === iso3);
          }) : [];

          if (!feats.length) {
            renderFallback();
          } else {
            renderProvinces({ type: 'FeatureCollection', features: feats });
          }
          showLoader(false);
        });
      }).catch(() => {
        renderFallback();
        showLoader(false);
      });
    }

    fetch('https://cdn.jsdelivr.net/gh/johan/world.geo.json@master/countries.geo.json')
      .then((r) => r.json())
      .then((data) => {
        worldLayer = L.geoJSON(data, {
          style: (f) => ({
            fillColor: colorFor(countryData[f.id] || 0),
            color: '#fefcff',
            weight: 0.5,
            fillOpacity: 0.92,
          }),
          onEachFeature: (feature, layer) => {
            const name = (feature.properties && feature.properties.name) || feature.id;
            const v = countryData[feature.id] || 0;
            layer.bindTooltip(
              `<span class="in-map-tt"><strong>${name}</strong><br>${v ? v + '% başvuru' : 'başvuru yok'}</span>`,
              { direction: 'auto', sticky: true, opacity: 1, className: 'in-map-tooltip' }
            );
            layer.on('mouseover', (e) => {
              e.target.setStyle({ weight: 1.6, color: '#11212d', fillOpacity: 1 });
              e.target.bringToFront();
            });
            layer.on('mouseout', () => worldLayer.resetStyle(layer));
            layer.on('click', () => drillInto(feature));
          },
        }).addTo(map);
      })
      .catch(() => { /* silent */ });

    setTimeout(() => map.invalidateSize(), 120);
  }

  function initWordcloud() {
    const el = document.getElementById('wordcloud');
    if (!el || typeof WordCloud === 'undefined') return;

    const list = [
      ['frontend geliştirici', 40],
      ['react', 30],
      ['istanbul', 26],
      ['uzaktan', 24],
      ['typescript', 22],
      ['junior', 20],
      ['startup', 18],
      ['ürün tasarımı', 16],
      ['yarı zamanlı', 14],
      ['staj', 14],
      ['node.js', 12],
      ['ui', 12],
      ['figma', 11],
      ['ankara', 10],
      ['mid-level', 9],
      ['css', 9],
      ['backend', 8],
      ['hibrit', 8],
      ['full-stack', 7],
    ];

    const goldDark = '#6a4818';
    const navy = '#11212d';
    const muted = '#30414f';
    const colorPool = [navy, goldDark, muted, '#b5935a', navy, muted];
    let idx = 0;

    WordCloud(el, {
      list,
      fontFamily: 'Helvetica Neue, Helvetica, Arial, sans-serif',
      fontWeight: '700',
      backgroundColor: 'transparent',
      color: () => colorPool[(idx++) % colorPool.length],
      rotateRatio: 0.15,
      rotationSteps: 2,
      gridSize: 8,
      weightFactor: 2.2,
      shrinkToFit: true,
      drawOutOfBound: false,
      minSize: 10,
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => waitForChart(init));
  } else {
    waitForChart(init);
  }
})();
