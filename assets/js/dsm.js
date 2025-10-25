// /assets/js/dsm.js
//import Chart from "https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js";

let charts = {};
let intervalId = null;

export function initDashboard() {
  destroyDashboard();

  const cpuCtx = document.getElementById("cpuChart");
  const ramCtx = document.getElementById("ramChart");
  const netCtx = document.getElementById("netChart");
  const diskCtx = document.getElementById("diskChart");

  if (!cpuCtx || !ramCtx || !diskCtx) {
    console.warn("Dashboard: elementos no encontrados");
    return;
  }

  charts = {
    cpu: new Chart(cpuCtx, {
      type: "line",
      data: { labels: [], datasets: [{ label: "CPU (%)", data: [], borderColor: "#007bff", tension: 0.3 }] },
      options: { scales: { y: { min: 0, max: 100 } }, plugins: { legend: { display: false } } },
    }),
    ram: new Chart(ramCtx, {
      type: "line",
      data: { labels: [], datasets: [{ label: "RAM (%)", data: [], borderColor: "#28a745", tension: 0.3 }] },
      options: { scales: { y: { min: 0, max: 100 } }, plugins: { legend: { display: false } } },
    }),
    net: new Chart(netCtx, {
      type: "line",
      data: { labels: [], datasets: [
        { label: "Rx KB/s", data: [], borderColor: "#17a2b8", tension: 0.3 },
        { label: "Tx KB/s", data: [], borderColor: "#ffc107", tension: 0.3 },
      ]},
      options: { scales: { y: { beginAtZero: true } } },
    }),
    disk: new Chart(diskCtx, {
      type: "bar",
      data: { labels: [], datasets: [{ label: "Uso (%)", data: [], backgroundColor: "#6f42c1" }] },
      options: { scales: { y: { min: 0, max: 100 } }, plugins: { legend: { display: false } } },
    }),
  };

  updateData();
  intervalId = setInterval(updateData, 5000);
}

export function destroyDashboard() {
  if (intervalId) {
    clearInterval(intervalId);
    intervalId = null;
  }
  Object.values(charts).forEach(c => c && c.destroy && c.destroy());
  charts = {};
}

async function updateData() {
  try {
    const res = await fetch("api/syno-status.php");
    // si tu API devuelve wrapper con success/data usar `const json = await res.json().then(j => j.data || j);`
    const jsonWrapped = await res.json();
    const json = jsonWrapped.data || jsonWrapped;
    if (!json) return;

    const time = new Date().toLocaleTimeString().slice(0, 5);

    // CPU: sumar user + system + other
    const cpu = (Number(json.cpu?.user_load) || 0)
              + (Number(json.cpu?.system_load) || 0)
              + (Number(json.cpu?.other_load) || 0);

    // RAM
    const ram = Number(json.memory?.real_usage) || 0;

    // NETWORK: network puede ser array. Tomamos "total" o el primero disponible
    let rx = 0, tx = 0;
    if (Array.isArray(json.network) && json.network.length > 0) {
      const netEntry = json.network.find(n => n.device === "total") || json.network[0];
      rx = (Number(netEntry.rx) || 0) / 1024; // KB/s
      tx = (Number(netEntry.tx) || 0) / 1024;
    } else if (json.network && typeof json.network === "object") {
      rx = (Number(json.network.rx) || 0) / 1024;
      tx = (Number(json.network.tx) || 0) / 1024;
    }

    // DISK + SPACE: construir lista combinada (discos físicos + volúmenes)
    const entries = []; // { label, value, source }
    // 1) discos físicos (json.disk.disk es array)
    if (Array.isArray(json.disk?.disk)) {
      json.disk.disk.forEach(d => {
        const label = d.display_name || d.device || "disk";
        const value = Number(d.utilization) || 0;
        entries.push({ label, value, source: "disk" });
      });
    }
    // 2) total si no hay discos individuales pero existe total
    if ((!Array.isArray(json.disk?.disk) || json.disk.disk.length === 0) && json.disk?.total) {
      entries.push({ label: "Total Disk", value: Number(json.disk.total.utilization) || 0, source: "disk" });
    }
    // 3) volúmenes (space.volume)
    if (Array.isArray(json.space?.volume)) {
      json.space.volume.forEach(v => {
        const label = v.display_name || v.device || "volume";
        const value = Number(v.utilization) || 0;
        entries.push({ label, value, source: "volume" });
      });
    }

    // Evitar duplicados por label: conservar primero encontrado
    const seen = new Set();
    const labels = [];
    const values = [];
    entries.forEach(e => {
      if (!seen.has(e.label)) {
        labels.push(e.label);
        values.push(e.value);
        seen.add(e.label);
      } else {
        // si ya existe, podríamos sumar o ignorar; por ahora ignoramos duplicados
      }
    });

    // --- actualizar charts ---
    pushData(charts.cpu, time, cpu);
    pushData(charts.ram, time, ram);
    pushMulti(charts.net, time, [rx, tx]);

    // actualizar gráfico de discos: reemplazamos labels y datos completos
    if (charts.disk) {
      charts.disk.data.labels = labels.length ? labels : ["N/A"];
      charts.disk.data.datasets[0].data = values.length ? values : [0];
      charts.disk.update();
    }

  } catch (e) {
    console.error("Error al actualizar datos DSM:", e);
  }
}

function pushData(chart, label, value) {
  if (!chart) return;
  const MAX = 15;
  if (!chart.data.labels) chart.data.labels = [];
  if (!chart.data.datasets || !chart.data.datasets[0]) return;
  if (chart.data.labels.length >= MAX) {
    chart.data.labels.shift();
    chart.data.datasets[0].data.shift();
  }
  chart.data.labels.push(label);
  chart.data.datasets[0].data.push(value);
  chart.update();
}

function pushMulti(chart, label, values) {
  if (!chart) return;
  const MAX = 15;
  if (!chart.data.labels) chart.data.labels = [];
  if (!chart.data.datasets) return;
  if (chart.data.labels.length >= MAX) {
    chart.data.labels.shift();
    chart.data.datasets.forEach(ds => ds.data.shift());
  }
  chart.data.labels.push(label);
  chart.data.datasets.forEach((ds, i) => {
    ds.data.push(values[i] != null ? values[i] : 0);
  });
  chart.update();
}
