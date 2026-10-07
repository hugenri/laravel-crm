<script setup>
import { computed } from 'vue';
import Button from './ui/Button.vue';
import InputError from './ui/InputError.vue';
import TextInput from './ui/TextInput.vue';
import { fromCents, lineAmount, taxAmount } from '../lib/money';

const props = defineProps({
    form: { type: Object, required: true },
    taxRate: { type: String, required: true },
    itemsMax: { type: Number, default: 50 },
});

const emit = defineEmits(['clear-error']);

/**
 * Vista previa de los totales mientras captura.
 *
 * El subtotal, el impuesto y el total que se guardan los calcula el servidor;
 * la aritmética está espejada en lib/money.js para que lo que se ve aquí
 * coincida con lo que se guarda. Una fila a medio escribir no aporta nada en
 * vez de aportar un cero, así un campo incompleto no parece un total más bajo.
 */
const subtotalCents = computed(() =>
    props.form.items.reduce((total, item) => {
        const amount = lineAmount(item.quantity, item.unit_price);

        return amount === null ? total : total + amount;
    }, 0),
);

const taxCents = computed(() => taxAmount(subtotalCents.value, props.taxRate));

const totalCents = computed(() => {
    const tax = taxCents.value;

    return tax === null ? null : subtotalCents.value + tax;
});

const totals = computed(() => [
    { label: 'Subtotal', value: fromCents(subtotalCents.value) },
    { label: `IVA (${props.taxRate}%)`, value: fromCents(taxCents.value) },
]);

function amountFor(index) {
    const item = props.form.items[index];
    const amount = lineAmount(item.quantity, item.unit_price);

    return amount === null ? '—' : `$${fromCents(amount)}`;
}

function addLine() {
    if (props.form.items.length >= props.itemsMax) {
        return;
    }

    props.form.items.push({ description: '', quantity: '', unit_price: '' });

    emit('clear-error', 'items');
}

/**
 * No se deja bajar de una fila: una cotizacion sin partidas no se puede guardar,
 * asi que quitar la ultima dejaria el formulario en un estado que el servidor va
 * a rechazar sin explicacion util.
 */
function removeLine(index) {
    if (props.form.items.length === 1) {
        return;
    }

    props.form.items.splice(index, 1);

    emit('clear-error', 'items');
}

/**
 * El indice viaja en el nombre del input para que el mensaje de error del servidor
 * aparezca en la fila correcta, y no junto al campo equivocado.
 */
function fieldId(index, field) {
    return `item-${index}-${field}`;
}

function itemError(index, field) {
    return props.form.errors[`items.${index}.${field}`] ?? null;
}
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-slate-500">
            Los productos y servicios se escriben libremente: el sistema no guarda un catálogo de
            productos, que es un módulo aparte.
        </p>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead>
                    <tr>
                        <th
                            scope="col"
                            class="w-10 px-2 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            #
                        </th>
                        <th
                            scope="col"
                            class="px-2 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            Descripción
                        </th>
                        <th
                            scope="col"
                            class="w-32 px-2 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            Cantidad
                        </th>
                        <th
                            scope="col"
                            class="w-40 px-2 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            Precio unitario
                        </th>
                        <th
                            scope="col"
                            class="w-32 px-2 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            Importe
                        </th>
                        <th scope="col" class="w-24 px-2 py-2">
                            <span class="sr-only">Quitar</span>
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    <tr v-for="(item, index) in form.items" :key="index">
                        <td class="px-2 py-2 text-sm text-slate-400">{{ index + 1 }}</td>
                        <td class="px-2 py-2">
                            <TextInput
                                :id="fieldId(index, 'description')"
                                :model-value="item.description"
                                autocomplete="off"
                                placeholder="Desarrollo de la plataforma"
                                :invalid="Boolean(itemError(index, 'description'))"
                                @update:model-value="item.description = $event; emit('clear-error', `items.${index}.description`)"
                            />
                            <InputError :message="itemError(index, 'description')" />
                        </td>
                        <td class="px-2 py-2">
                            <TextInput
                                :id="fieldId(index, 'quantity')"
                                :model-value="item.quantity"
                                type="text"
                                inputmode="decimal"
                                placeholder="1"
                                :invalid="Boolean(itemError(index, 'quantity'))"
                                @update:model-value="item.quantity = $event; emit('clear-error', `items.${index}.quantity`)"
                            />
                            <InputError :message="itemError(index, 'quantity')" />
                        </td>
                        <td class="px-2 py-2">
                            <TextInput
                                :id="fieldId(index, 'unit_price')"
                                :model-value="item.unit_price"
                                type="text"
                                inputmode="decimal"
                                placeholder="0.00"
                                :invalid="Boolean(itemError(index, 'unit_price'))"
                                @update:model-value="item.unit_price = $event; emit('clear-error', `items.${index}.unit_price`)"
                            />
                            <InputError :message="itemError(index, 'unit_price')" />
                        </td>
                        <td class="whitespace-nowrap px-2 py-2 text-right text-sm text-slate-900">
                            {{ amountFor(index) }}
                        </td>
                        <td class="px-2 py-2 text-right">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                :disabled="form.items.length === 1"
                                @click="removeLine(index)"
                            >
                                Quitar
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <Button
                type="button"
                variant="secondary"
                :disabled="form.items.length >= itemsMax"
                @click="addLine"
            >
                Agregar partida
            </Button>

            <p v-if="form.errors.items" class="text-sm text-red-600">{{ form.errors.items }}</p>
        </div>

        <div class="flex justify-end">
            <dl class="w-full max-w-xs space-y-1.5 border-t border-slate-200 pt-4">
                <div
                    v-for="row in totals"
                    :key="row.label"
                    class="flex items-center justify-between text-sm"
                >
                    <dt class="text-slate-500">{{ row.label }}</dt>
                    <dd class="font-medium text-slate-900">
                        {{ row.value === null ? '—' : `$${row.value}` }}
                    </dd>
                </div>

                <div class="flex items-center justify-between border-t border-slate-200 pt-2">
                    <dt class="text-sm font-semibold text-slate-900">Total</dt>
                    <dd class="text-base font-semibold text-slate-900">
                        {{ totalCents === null ? '—' : `$${fromCents(totalCents)}` }}
                    </dd>
                </div>
            </dl>
        </div>

        <p class="text-right text-xs text-slate-500">
            El subtotal, el IVA y el total los calcula el sistema al guardar. Esta vista previa
            solo sirve para ver el total mientras captura.
        </p>
    </div>
</template>
