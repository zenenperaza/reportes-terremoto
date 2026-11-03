(() => {
    'use strict';
    const normalize = value => String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
    const units = ['cero', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciseis', 'diecisiete', 'dieciocho', 'diecinueve'];
    function ageNumber(value) {
        const text = normalize(value).replace(/\s*anos?(?:\s+de\s+edad)?$/, '').trim();
        if (/^\d{1,3}$/.test(text)) return Number(text);
        if (units.includes(text)) return units.indexOf(text);
        if (text === 'cien' || text === 'ciento') return 100;
        if (text.startsWith('ciento ')) {const rest = ageNumber(text.slice(7)); return rest !== null && rest <= 20 ? 100 + rest : null;}
        if (text.startsWith('veinti')) {const unit = units.indexOf(text.slice(6)); return unit > 0 && unit < 10 ? 20 + unit : null;}
        const tens = {veinte: 20, treinta: 30, cuarenta: 40, cincuenta: 50, sesenta: 60, setenta: 70, ochenta: 80, noventa: 90};
        const [ten, unit, extra] = text.split(' y ');
        if (tens[ten] && unit === undefined) return tens[ten];
        const digit = units.indexOf(unit);
        return tens[ten] && digit > 0 && digit < 10 && extra === undefined ? tens[ten] + digit : null;
    }
    function digits(value, nationalId = false) {
        let text = normalize(value).replace(/^numero\s+/, '');
        let prefix = '';
        if (nationalId) {
            const match = text.match(/^(venezolan[oa]|extranjer[oa]|uve|ve|v|e)(?=[\s.\-\d]|$)[\s.\-]*/);
            if (match) {prefix = /^(e|extranj)/.test(match[1]) ? 'E' : 'V'; text = text.slice(match[0].length);}
        } else if (text.startsWith('+')) {prefix = '+'; text = text.slice(1);}
        const pieces = text.replace(/[.()\-]/g, ' ').split(/\s+/).filter(Boolean);
        const result = pieces.map(piece => /^\d+$/.test(piece) ? piece : piece === 'un' ? '1' : units.indexOf(piece) >= 0 && units.indexOf(piece) < 10 ? String(units.indexOf(piece)) : '?').join('');
        return new RegExp(nationalId ? '^\\d{5,12}$' : '^\\d{7,20}$').test(result) ? prefix + result : null;
    }
    function parse(raw, options = {}) {
        let text = String(raw).normalize('NFC').trim();
        // Only an explicit, final command is actionable; a negated phrase is never a command.
        const command = /(?:^|[,;.!]\s*|\bcomando\s+)(?:guardar|guarda)(?:\s+el)?\s+beneficiari[oa][.!]?\s*$/i;
        const saveRequested = command.test(text);
        if (saveRequested) text = text.replace(command, '').trim();
        const values = {}, issues = [];
        const labels = [
            ['caregiver_national_id', 'c[eé]dula\\s+(?:(?:del?|de la)\\s+)?cuidador(?:a)?'],
            ['caregiver_full_name', 'nombre(?:\\s+y\\s+apellidos?)?\\s+(?:(?:del?|de la)\\s+)?cuidador(?:a)?|cuidador(?:a)?'],
            ['caregiver_relationship', 'parentesco(?:\\s+(?:(?:del?|de la)\\s+)?cuidador(?:a)?)?'],
            ['national_id', 'sin c[eé]dula|c[eé]dula(?:\\s+(?:del?\\s+)?beneficiari[oa])?|identidad|ci'],
            ['full_name', 'nombre(?:\\s+y\\s+apellidos?)?'],
            ['age', 'edad|(?:\\d{1,3}|(?:ciento?|cero|un[oa]?|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|once|doce|trece|catorce|quince|dieci\\p{L}+|veinte|veinti\\p{L}+|treinta|cuarenta|cincuenta|sesenta|setenta|ochenta|noventa)(?:\\s+(?:y|un[oa]?|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|veinte))*)\\s+a[ñn]os?'],
            ['sex', 'sexo|g[eé]nero|mujer|femenin[oa]|niña|hombre|masculin[oa]|niño'],
            ['phone', 'tel[eé]fono|celular|m[oó]vil'], ['nationality', 'nacionalidad'],
            ['disability', 'sin discapacidad|ninguna discapacidad|discapacidad(?:es)?'],
            ['ethnicity', 'no ind[ií]gena|sin etnia|ninguna etnia|ind[ií]gena|etnia'],
            ['pregnant_lactating', 'ni embarazada ni lactante|no embarazada|no lactante|embarazada o lactante|embarazada|lactante|gestante|amamantando'],
            ['is_recurrent', 'no recurrente|atendid[oa] anteriormente|ya atendid[oa]|recurrente'],
        ];
        const pattern = new RegExp('\\b(?:' + labels.map(([, regex]) => '(?:' + regex + ')').join('|') + ')(?=[\\s:,.!?;]|$)', 'giu');
        const matches = [...text.matchAll(pattern)];
        const choose = (value, available, aliases = {}) => {
            const normalized = normalize(value);
            const alias = aliases[normalized];
            return (available || []).find(option => normalize(option) === normalized || (alias && normalize(option) === normalize(alias)));
        };
        const sexValues = {mujer: 'Mujer', femenina: 'Mujer', femenino: 'Mujer', nina: 'Mujer', hombre: 'Hombre', masculino: 'Hombre', nino: 'Hombre'};
        const name = (field, value) => {
            const clean = value.replace(/^(?:es|se llama)\s+/i, '').trim();
            if (clean && clean.length <= 150 && /^[\p{L}\s'’-]+$/u.test(clean)) values[field] = clean.toLocaleUpperCase('es-VE');
            else issues.push(field === 'full_name' ? 'Revise el nombre y apellido del beneficiario.' : 'Revise el nombre del cuidador.');
        };
        const bare = value => {
            const normalized = normalize(value);
            if (!normalized) return;
            if (sexValues[normalized]) {values.sex = sexValues[normalized]; return;}
            if (/\banos?(?:\s+de\s+edad)?$/.test(normalized)) {
                const age = ageNumber(value);
                if (age !== null && age <= 120) values.age = String(age);
                else issues.push('Revise la edad del beneficiario.');
                return;
            }
            if (!values.full_name && value.trim().split(/\s+/).length >= 2 && !/^(?:no|sin|guardar|guarda|comando)\b/.test(normalized)) name('full_name', value);
        };
        const parts = value => value.split(/[,;]/).map(piece => piece.trim()).filter(Boolean);
        parts(text.slice(0, matches[0]?.index ?? text.length)).forEach(bare);
        let caregiverScope = false;
        matches.forEach((match, index) => {
            const key = labels.find(([, regex]) => new RegExp('^(?:' + regex + ')$', 'iu').test(match[0]))[0];
            const label = normalize(match[0]);
            if (key.startsWith('caregiver_')) caregiverScope = true;
            else if (['national_id', 'full_name'].includes(key)) caregiverScope = false;
            if (caregiverScope && ['age', 'sex'].includes(key)) return;
            const valueParts = parts(text.slice(match.index + match[0].length, matches[index + 1]?.index ?? text.length).replace(/^[\s:]+/, ''));
            let value = (valueParts.shift() || '').replace(/^es\s+/i, '').replace(/[.!]+$/, '').trim();
            // "edad 34 años" / "sexo mujer" contain a label followed by a self-describing token.
            if (!value && ['age', 'sex'].includes(key) && matches[index + 1] &&
                labels.find(([, regex]) => new RegExp('^(?:' + regex + ')$', 'iu').test(matches[index + 1][0]))[0] === key) return;
            if (key === 'full_name') {
                // Accommodate an age spoken without the word "edad" after a labeled name.
                const trailingAge = value.match(/^(.+?)\s+(\d{1,3}\s*años?)$/i);
                if (trailingAge) {value = trailingAge[1]; bare(trailingAge[2]);}
                name(key, value);
            } else if (key === 'caregiver_full_name') name(key, value);
            else if (key === 'national_id' && label === 'sin cedula') values.national_id = '';
            else if (key === 'national_id' || key === 'caregiver_national_id' || key === 'phone') {
                let result = digits(value, key !== 'phone');
                if (result === null && key === 'national_id') {
                    const withName = value.match(/^([VE]?[\d\s.\-]+)\s+([\p{L}][\p{L}\s'’-]+)$/iu);
                    if (withName && digits(withName[1], true)) {
                        result = digits(withName[1], true);
                        name('full_name', withName[2]);
                    }
                }
                if (result !== null) values[key] = result;
                else issues.push(key === 'phone' ? 'Revise el teléfono; dicte las cifras o cada dígito por separado.' : key === 'national_id' ? 'Revise la cédula del beneficiario.' : 'Revise la cédula del cuidador.');
            } else if (key === 'age') {
                const age = ageNumber(label === 'edad' ? value : label);
                if (age !== null && age <= 120) values.age = String(age);
                else issues.push('Revise la edad del beneficiario.');
            } else if (key === 'sex') {
                if (sexValues[label] || sexValues[normalize(value)]) values.sex = sexValues[label] || sexValues[normalize(value)];
                else issues.push('Revise el sexo del beneficiario.');
            } else if (key === 'caregiver_relationship') {
                const selected = choose(value, options.caregiver_relationships || ['Madre', 'Padre', 'Abuela', 'Abuelo', 'Tía', 'Tío', 'Hermana', 'Hermano', 'Madrastra', 'Padrastro', 'Tutor/a legal', 'Otro familiar', 'Cuidador/a no familiar', 'Otro'], {tutor: 'Tutor/a legal', tutora: 'Tutor/a legal', 'tutor legal': 'Tutor/a legal', 'tutora legal': 'Tutor/a legal'});
                if (selected) values[key] = selected;
                else issues.push('Seleccione un parentesco del cuidador disponible.');
            } else if (key === 'nationality') {
                const selected = choose(value, options.nationalities || ['Venezolana', 'Colombiana', 'Otra']);
                if (selected) values[key] = selected;
                else if (value) issues.push('Seleccione una nacionalidad disponible.');
            } else if (key === 'disability') {
                const aliases = {ninguna: 'Ninguna', no: 'Ninguna', fisica: 'Discapacidad Física o Motora', motora: 'Discapacidad Física o Motora', motriz: 'Discapacidad Física o Motora', auditiva: 'Discapacidad Auditiva', sorda: 'Discapacidad Auditiva', visual: 'Discapacidad Visual', ciega: 'Discapacidad Visual', intelectual: 'Discapacidad Intelectual', psiquica: 'Discapacidad Psíquica', sensorial: 'Discapacidad Sensorial (auditiva y visual)'};
                const selected = /^(sin|ninguna) discapacidad$/.test(label) ? 'Ninguna' : choose(value, options.disabilities || ['Ninguna', ...Object.values(aliases)], aliases);
                if (selected) values[key] = selected;
                else if (value) issues.push('Seleccione una discapacidad disponible.');
            } else if (key === 'ethnicity') {
                const selected = /^(no indigena|sin etnia|ninguna etnia)$/.test(label) ? 'Ninguna' : choose(value, options.ethnicities || ['Ninguna'], {no: 'Ninguna'});
                if (selected) values[key] = selected;
                else if (value) issues.push('Seleccione una etnia disponible.');
            } else if (key === 'pregnant_lactating') {
                values[key] = /^(no |ni )/.test(label) || /^no$/.test(normalize(value)) ? 'No' : /^ninguna$/.test(normalize(value)) ? 'Ninguna' : 'Sí';
            } else if (key === 'is_recurrent') values[key] = label === 'no recurrente' || normalize(value) === 'no' ? '0' : '1';
            // Never infer beneficiary age, sex or name from the caregiver's own description.
            if (!key.startsWith('caregiver_')) valueParts.forEach(bare);
        });
        return {values, issues: [...new Set(issues)], saveRequested};
    }

    function createCoordinator({inputs, options = {}, lookup, sync = () => {}, status = () => {}, pending = () => {}}) {
        let sequence = 0;
        const applied = new Map(), dictated = new Map();
        const cancel = () => {sequence++; applied.clear(); dictated.clear(); pending(false);};
        async function apply(text) {
            const current = ++sequence;
            const result = parse(text, options);
            const before = Object.fromEntries(Object.entries(inputs).map(([name, input]) => [name, input.value]));
            pending(true);
            let lookupResult = {status: 'not-requested', fields: []};
            try {
                if (Object.hasOwn(result.values, 'national_id')) {
                    if (result.values.national_id) status('Consultando primero la cédula dictada…');
                    lookupResult = await lookup(result.values.national_id) || lookupResult;
                }
                if (current !== sequence || lookupResult.status === 'cancelled') return {cancelled: true};
                const recognized = [];
                for (const [field, value] of Object.entries(result.values)) {
                    const input = inputs[field];
                    if (!input || input.readOnly) continue;
                    // Lookup changes are expected. Manual edits during an awaited lookup are not overwritten.
                    const changedManually = input.value !== before[field] && !(lookupResult.fields || []).includes(field) && field !== 'national_id';
                    const correctedEarlier = dictated.get(field) === value && applied.has(field) && input.value !== applied.get(field);
                    if (changedManually || correctedEarlier) continue;
                    if (input.options && !Array.from(input.options).some(option => option.value === value && !option.disabled)) {
                        result.issues.push('Revise las opciones seleccionadas en el formulario.');
                        continue;
                    }
                    input.value = value;
                    applied.set(field, input.value);
                    dictated.set(field, value);
                    recognized.push(field);
                }
                sync();
                const failedLookup = ['ambiguous', 'error'].includes(lookupResult.status);
                status(`Se completaron ${recognized.length} campos dictados. Revise cédula, edad, teléfono y cuidador antes de guardar. ${result.issues.join(' ')}${failedLookup ? ' La consulta de la cédula requiere revisión manual.' : ''}`);
                return {...result, recognized, cancelled: false, canSave: !failedLookup && result.issues.length === 0};
            } catch (_) {
                if (current !== sequence) return {cancelled: true};
                status('No se pudo completar el dictado. Revise los datos y vuelva a intentarlo.');
                return {cancelled: false, recognized: [], canSave: false};
            } finally {if (current === sequence) pending(false);}
        }
        return {apply, cancel};
    }
    window.BeneficiaryVoice = {parse, createCoordinator};
})();
