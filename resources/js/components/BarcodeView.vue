<script setup lang="ts">
import JsBarcode from 'jsbarcode';
import { onMounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        value: string;
        height?: number;
        width?: number;
        displayValue?: boolean;
        fontSize?: number;
        textMargin?: number;
        margin?: number;
    }>(),
    {
        height: 32,
        width: 1.2,
        displayValue: true,
        fontSize: 10,
        textMargin: 2,
        margin: 2,
    },
);

const el = ref<SVGSVGElement | null>(null);

function render() {
    if (!el.value || !props.value) return;
    const raw = String(props.value).replace(/\s+/g, '').trim();
    if (!raw) return;

    // Jika 12 atau 13 digit numerik, gunakan format EAN-13 retail standar
    // Format ini otomatis menampilkan teks seperti '8 993988 283294' dengan guard bar
    if (/^\d{12,13}$/.test(raw)) {
        try {
            JsBarcode(el.value, raw, {
                format: 'EAN13',
                displayValue: props.displayValue,
                fontSize: props.fontSize,
                font: 'monospace',
                fontOptions: 'bold',
                textMargin: props.textMargin,
                height: props.height,
                width: props.width,
                margin: props.margin,
                background: 'transparent',
                lineColor: '#0f172a',
                flat: false,
            });
            return;
        } catch {
            // Fallback ke CODE128 jika checksum EAN13 tidak cocok
        }
    }

    try {
        JsBarcode(el.value, raw, {
            format: 'CODE128',
            displayValue: props.displayValue,
            fontSize: props.fontSize,
            font: 'monospace',
            fontOptions: 'bold',
            textMargin: props.textMargin,
            height: props.height,
            width: props.width,
            margin: props.margin,
            background: 'transparent',
            lineColor: '#0f172a',
            flat: true,
        });
    } catch {
        /* abaikan barcode tidak valid */
    }
}

onMounted(render);
watch([() => props.value, () => props.displayValue, () => props.height, () => props.width], render);
</script>

<template>
    <svg ref="el" class="max-w-full inline-block align-middle" />
</template>
