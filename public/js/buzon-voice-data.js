(() => {
    'use strict';
    const normalize = text => String(text).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
    const small = ['cero', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciseis', 'diecisiete', 'dieciocho', 'diecinueve'];
    const number = text => {
        const value = normalize(text).replace(/\banos?\b/g, '').trim();
        if (/^\d{1,3}$/.test(value)) return Number(value);
        if (small.includes(value)) return small.indexOf(value);
        if (value === 'cien' || value === 'ciento') return 100;
        if (value.startsWith('ciento ')) {const rest = number(value.slice(7)); return rest !== null && rest <= 20 ? 100 + rest : null;}
        if (value.startsWith('veinti')) {const unit = small.indexOf(value.slice(6)); return unit > 0 && unit < 10 ? 20 + unit : null;}
        const tens = {veinte: 20, treinta: 30, cuarenta: 40, cincuenta: 50, sesenta: 60, setenta: 70, ochenta: 80, noventa: 90};
        const parts = value.split(' y ');
        if (tens[parts[0]] && parts.length === 1) return tens[parts[0]];
        const unit = small.indexOf(parts[1]);
        return parts.length === 2 && tens[parts[0]] && unit > 0 && unit < 10 ? tens[parts[0]] + unit : null;
    };
    const parse = text => {
        const raw = String(text).normalize('NFC').trim();
        const values = {}, issues = [];
        const matches = [...raw.matchAll(/\b(proyecto|fecha(?:\s+de\s+(?:la\s+)?opini[oó]n)?|lugar(?:\s+de\s+(?:la\s+)?(?:queja|sugerencia))?|nombre(?:\s+y\s+apellidos?)?|tel[eé]fono|celular|edad|g[eé]nero|sexo|discapacidad(?:es)?|afrodescendiente|valoraci[oó]n|opini[oó]n|tipo(?:\s+de\s+retroalimentaci[oó]n)?)(?=\s|:|,|$)/giu)];
        const chunks = {};
        matches.forEach((match, index) => {
            const key = normalize(match[1]).split(' ')[0];
            const value = raw.slice(match.index + match[0].length, matches[index + 1]?.index ?? raw.length)
                .replace(/^[\s:,;]+|[\s,;.]+$/g, '').replace(/^es\s+/i, '');
            chunks[key] = value;
        });
        // Keep project/date as delimiters, but never return them as dictated fields.
        // This also prevents their contents from being appended to a preceding name or place.
        if (chunks.lugar) {
            if (chunks.lugar.length <= 200) values.place_name = chunks.lugar;
            else issues.push('El lugar supera los 200 caracteres.');
        }
        const leading = raw.slice(0, matches[0]?.index ?? raw.length).split(/[,;]/)[0].trim();
        const name = (chunks.nombre || (/[,;]/.test(raw) && /^[\p{L}\s'-]+$/u.test(leading) && leading.split(/\s+/).length >= 2 ? leading : '')).split(/[,;]/)[0].trim();
        if (name) {
            if (normalize(name) === 'sin nombre' || normalize(name) === 'anonimo' || normalize(name) === 'anonima') values.full_name = '';
            else if (name.length <= 150) values.full_name = name;
            else issues.push('El nombre supera los 150 caracteres.');
        }
        const age = chunks.edad || normalize(raw).match(/(?:^|[,;]\s*)([a-z\s]+|\d{1,3})\s+anos?\b/)?.[1];
        if (age) {
            const ageNumber = number(age.split(/[,;]/)[0]);
            if (ageNumber !== null && ageNumber >= 0 && ageNumber <= 120) values.age = String(ageNumber);
            else issues.push('Revise la edad: debe estar entre 0 y 120 años.');
        }
        const gender = normalize(chunks.genero || chunks.sexo || (normalize(raw).match(/(?:^|[,;])\s*(mujer|hombre|femenino|masculino|no binario|otro)\s*(?=[,;]|$)/)?.[1] ?? ''));
        const genders = {mujer: 'female', femenino: 'female', femenina: 'female', hombre: 'male', masculino: 'male', masculina: 'male', 'no binario': 'non_binary_other', 'no binaria': 'non_binary_other', otro: 'non_binary_other', 'no indicado': ''};
        if (gender) {
            if (Object.hasOwn(genders, gender)) values.gender = genders[gender];
            else issues.push('Revise el género: no se reconoció una opción del formulario.');
        }
        const phone = chunks.telefono || chunks.celular;
        if (phone) {
            const parts = normalize(phone).replace(/[+().-]/g, ' ').split(/\s+/).filter(Boolean);
            const digits = parts.map(part => /^\d+$/.test(part) ? part : (part === 'un' ? '1' : small.indexOf(part) >= 0 && small.indexOf(part) < 10 ? String(small.indexOf(part)) : '?')).join('');
            if (/^\d{7,20}$/.test(digits)) values.phone = phone.trim().startsWith('+') ? '+' + digits : digits;
            else issues.push('Revise el teléfono: use cifras o dicte cada dígito por separado.');
        }
        const disability = normalize(chunks.discapacidad || chunks.discapacidades || '');
        if (disability) {
            const options = {visual: 'visual', auditiva: 'hearing', motriz: 'motor', motora: 'motor', fisica: 'motor'};
            if (/^(?:ninguna|sin discapacidad|no|ninguna discapacidad)$/.test(disability)) values.disabilities = ['none'];
            else {
                const aliases = disability.split(/\s*(?:,|\by\b)\s*/).filter(Boolean);
                if (aliases.every(alias => options[alias])) values.disabilities = [...new Set(aliases.map(alias => options[alias]))];
                else issues.push('Revise la discapacidad: no se reconocieron todas las opciones.');
            }
        }
        if (chunks.afrodescendiente) {
            const afro = normalize(chunks.afrodescendiente);
            if (['si', 'no', 'no indicado'].includes(afro)) values.afro_descendant = {si: '1', no: '0', 'no indicado': ''}[afro];
            else issues.push('Revise la opción Afrodescendiente.');
        }
        const opinion = normalize(chunks.valoracion || chunks.opinion || '');
        if (opinion) {
            const options = {'muy buena': 'very_good', 'ni buena ni mala': 'neutral', mala: 'bad'};
            if (options[opinion]) values.opinion = options[opinion];
            else issues.push('Revise la valoración de la actividad.');
        }
        if (chunks.tipo) {
            const options = {sugerencia: 'suggestion', comentario: 'comment', queja: 'complaint'};
            const value = options[normalize(chunks.tipo)];
            if (value) values.feedback_type = value;
            else issues.push('Revise el tipo de retroalimentación.');
        }
        return {values, issues};
    };
    window.BuzonVoiceData = {parse};
})();
