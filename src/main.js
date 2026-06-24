const storeKey = 'gestione-soci-cantieri';
const tomorrow = () => {
  const d = new Date();
  d.setDate(d.getDate() + 1);
  return d.toISOString().slice(0, 10);
};

const initialState = {
  sites: [],
  vehicles: [],
  workers: [],
  orders: {},
};

let state = loadState();
let currentOrder = blankOrder(tomorrow());

function loadState() {
  try {
    return { ...initialState, ...JSON.parse(localStorage.getItem(storeKey) || '{}') };
  } catch {
    return structuredClone(initialState);
  }
}

function persist() {
  localStorage.setItem(storeKey, JSON.stringify(state));
}

function blankOrder(date) {
  return {
    date,
    sites: [],
    absences: { malati: '', ferie: '', permessi: '', altre: '' },
  };
}

function uid(prefix) {
  return `${prefix}-${crypto.randomUUID ? crypto.randomUUID() : Date.now()}`;
}

function optionList(items, label, selected = '') {
  const empty = `<option value="">${label}</option>`;
  return empty + items.map(item => `<option value="${item.id}" ${item.id === selected ? 'selected' : ''}>${item.name}</option>`).join('');
}

function setupTabs() {
  document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.tab, .panel').forEach(el => el.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById(tab.dataset.tab).classList.add('active');
    });
  });
}

function setupForms() {
  document.getElementById('site-form').addEventListener('submit', event => {
    event.preventDefault();
    state.sites.push({ id: uid('site'), name: value('site-name'), place: value('site-place'), contact: value('site-contact') });
    event.target.reset();
    persist();
    render();
  });

  document.getElementById('vehicle-form').addEventListener('submit', event => {
    event.preventDefault();
    state.vehicles.push({ id: uid('vehicle'), name: value('vehicle-name'), plate: value('vehicle-plate'), owner: value('vehicle-owner') });
    event.target.reset();
    persist();
    render();
  });

  document.getElementById('worker-form').addEventListener('submit', event => {
    event.preventDefault();
    state.workers.push({ id: uid('worker'), name: value('worker-name'), active: true, ordinary: true });
    event.target.reset();
    persist();
    render();
  });
}

function value(id) {
  return document.getElementById(id).value.trim();
}

function setupOrder() {
  const dateInput = document.getElementById('service-date');
  dateInput.value = tomorrow();
  dateInput.addEventListener('change', () => loadOrder(dateInput.value));
  document.getElementById('add-site-section').addEventListener('click', () => {
    currentOrder.sites.push({ siteId: '', assignments: [] });
    renderOrder();
  });
  document.getElementById('save-order').addEventListener('click', saveCurrentOrder);
  ['malati', 'ferie', 'permessi', 'altre'].forEach(kind => {
    document.getElementById(`abs-${kind}`).addEventListener('input', event => {
      currentOrder.absences[kind] = event.target.value;
    });
  });
  loadOrder(dateInput.value);
}

function loadOrder(date) {
  currentOrder = structuredClone(state.orders[date] || blankOrder(date));
  renderOrder();
}

function saveCurrentOrder() {
  collectOrderFromDom();
  state.orders[currentOrder.date] = currentOrder;
  persist();
  alert('Ordine di servizio salvato.');
}

function collectOrderFromDom() {
  currentOrder.date = document.getElementById('service-date').value;
  currentOrder.sites = [...document.querySelectorAll('.order-site')].map(section => ({
    siteId: section.querySelector('.order-site-select').value,
    assignments: [...section.querySelectorAll('tbody tr')].map(row => ({
      workerId: row.querySelector('.worker-select').value,
      schedulePlace: row.querySelector('.schedule-place').value,
      vehicleId: row.querySelector('.vehicle-select').value,
    })),
  }));
}

function render() {
  renderList('sites-list', state.sites, item => `${item.name}<small>${[item.place, item.contact].filter(Boolean).join(' · ')}</small>`, 'sites');
  renderList('vehicles-list', state.vehicles, item => `${item.name}<small>${[item.plate, item.owner].filter(Boolean).join(' · ')}</small>`, 'vehicles');
  renderList('workers-list', state.workers, item => `${item.name}<small>Socio ordinario lavoratore attivo</small>`, 'workers');
  renderOrder();
}

function renderList(containerId, items, content, collection) {
  document.getElementById(containerId).innerHTML = items.length ? items.map(item => `
    <article class="list-card"><div>${content(item)}</div><button data-delete="${collection}:${item.id}" class="danger">Elimina</button></article>
  `).join('') : '<p class="empty">Nessun elemento inserito.</p>';
  document.querySelectorAll('[data-delete]').forEach(button => button.onclick = () => {
    const [key, id] = button.dataset.delete.split(':');
    state[key] = state[key].filter(item => item.id !== id);
    persist();
    render();
  });
}

function renderOrder() {
  document.getElementById('service-date').value = currentOrder.date;
  ['malati', 'ferie', 'permessi', 'altre'].forEach(kind => document.getElementById(`abs-${kind}`).value = currentOrder.absences[kind] || '');
  const container = document.getElementById('order-sites');
  container.innerHTML = '';
  currentOrder.sites.forEach((site, index) => container.appendChild(renderOrderSite(site, index)));
  if (!currentOrder.sites.length) container.innerHTML = '<p class="empty">Aggiungi un cantiere per iniziare l\'ordine di servizio.</p>';
}

function renderOrderSite(site, index) {
  const node = document.getElementById('order-site-template').content.cloneNode(true);
  const section = node.querySelector('.order-site');
  section.querySelector('.order-site-select').innerHTML = optionList(state.sites, 'Seleziona cantiere', site.siteId);
  section.querySelector('.order-site-select').onchange = event => currentOrder.sites[index].siteId = event.target.value;
  section.querySelector('.remove-site').onclick = () => { currentOrder.sites.splice(index, 1); renderOrder(); };
  const tbody = section.querySelector('tbody');
  site.assignments.forEach((assignment, rowIndex) => tbody.appendChild(renderAssignment(index, rowIndex, assignment)));
  section.querySelector('.add-assignment').onclick = () => {
    currentOrder.sites[index].assignments.push({ workerId: '', schedulePlace: '', vehicleId: '' });
    renderOrder();
  };
  return node;
}

function renderAssignment(siteIndex, rowIndex, assignment) {
  const row = document.createElement('tr');
  row.innerHTML = `
    <td><select class="worker-select">${optionList(activeWorkers(), 'Seleziona persona', assignment.workerId)}</select></td>
    <td><input class="schedule-place" value="${assignment.schedulePlace || ''}" placeholder="Es. 07:30 magazzino, poi Via Roma" /></td>
    <td><select class="vehicle-select">${optionList(state.vehicles, 'Seleziona mezzo', assignment.vehicleId)}</select></td>
    <td><button class="danger" type="button">Elimina</button></td>`;
  row.querySelector('.worker-select').onchange = event => assignment.workerId = event.target.value;
  row.querySelector('.schedule-place').oninput = event => assignment.schedulePlace = event.target.value;
  row.querySelector('.vehicle-select').onchange = event => assignment.vehicleId = event.target.value;
  row.querySelector('button').onclick = () => { currentOrder.sites[siteIndex].assignments.splice(rowIndex, 1); renderOrder(); };
  return row;
}

function activeWorkers() {
  return state.workers.filter(worker => worker.active && worker.ordinary);
}

setupTabs();
setupForms();
setupOrder();
render();

export { blankOrder, optionList };
