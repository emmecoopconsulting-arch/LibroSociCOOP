import test from 'node:test';
import assert from 'node:assert/strict';

test('ordine vuoto inizializza data e assenze', () => {
  const order = {
    date: '2026-06-25',
    sites: [],
    absences: { malati: '', ferie: '', permessi: '', altre: '' },
  };

  assert.equal(order.date, '2026-06-25');
  assert.deepEqual(Object.keys(order.absences), ['malati', 'ferie', 'permessi', 'altre']);
});
