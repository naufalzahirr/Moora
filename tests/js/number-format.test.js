import test from 'node:test';
import assert from 'node:assert/strict';
import { parseCurrency } from '../../resources/js/number-format.js';

test('local currency and existing decimal amounts retain their value', () => {
    assert.equal(parseCurrency('Rp7.159.600'), 7159600);
    assert.equal(parseCurrency('7159600.00'), 7159600);
    assert.equal(parseCurrency('1.250,50'), 1250.5);
    assert.equal(parseCurrency('1250.50'), 1250.5);
});

test('empty or malformed amounts cannot be mistaken for a completed zero', () => {
    for (const input of ['', ' ', 'Rp', 'abc12', '1.2.3', '12,34,56']) {
        assert.equal(parseCurrency(input), null);
    }
    assert.equal(parseCurrency('0'), 0);
});

test('negative signs remain visible to nonnegative validation', () => {
    assert.equal(parseCurrency('-1.000'), -1000);
    assert.equal(parseCurrency('-10'), -10);
});
