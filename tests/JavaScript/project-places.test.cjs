const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const projectView = fs.readFileSync('resources/views/proyectos/form.blade.php', 'utf8');
const reportView = fs.readFileSync('resources/views/reports/create.blade.php', 'utf8');

test('project configuration filters places by states and optional municipalities and retains only compatible selections', () => {
    const start = projectView.indexOf('const syncPlaces =');
    const end = projectView.indexOf('const syncMunicipalities =', start);
    const selectedStates = ['1'], selectedMunicipalities = [];
    const all = [
        {value: '10', dataset: {stateId: '1', municipalityId: '11'}, selected: true},
        {value: '20', dataset: {stateId: '1', municipalityId: '12'}, selected: true},
        {value: '30', dataset: {stateId: '2', municipalityId: '21'}, selected: false},
    ];
    const places = {
        options: [...all],
        get 0() {return this;},
        val() {return this.options.filter(option => option.selected).map(option => option.value);},
        empty() {this.options = [];}, append(option) {this.options.push(option);}, prepend(option) {this.options.unshift(option);},
        prop(name, value) {this[name] = value; return this;}, trigger() {return this;},
    };
    let sync;
    vm.runInNewContext(projectView.slice(start, end) + '\nexpose(syncPlaces);', {
        states: {val: () => selectedStates}, municipalities: {val: () => selectedMunicipalities}, places,
        selectAllPlacesValue: '__all_project_places__',
        Option: function (text, value) {return {text, value, selected: false};},
        allPlaces: {each(handler) {all.forEach(option => handler.call(option));}},
        $(option) {return {clone() {return {value: option.value, dataset: {...option.dataset}, selected: option.selected,
            prop(name, value) {this[name] = value; return this;}};}};},
        expose(value) {sync = value;},
    });
    sync();
    assert.deepEqual(places.options.map(option => option.value), ['__all_project_places__', '10', '20']);
    assert.deepEqual(places.val(), ['10', '20']);
    selectedMunicipalities.push('11');
    sync();
    assert.deepEqual(places.val(), ['10']);
    assert.deepEqual(places.options.map(option => option.value), ['__all_project_places__', '10']);
    selectedStates.splice(0, 1, '2');
    selectedMunicipalities.length = 0;
    sync();
    assert.deepEqual(places.options.map(option => option.value), ['__all_project_places__', '30']);
    assert.deepEqual(places.val(), []);
    selectedStates.length = 0;
    sync();
    assert.equal(places.options.length, 0);
    assert.equal(places.disabled, true);
    assert.ok(projectView.includes("municipalities.on('change', syncPlaces)"));
    assert.ok(projectView.includes('syncPlaces();'));
});

test('select all is a one-time action on available places and individual removals remain removed', () => {
    const start = projectView.indexOf('const selectAllAvailablePlaces =');
    const end = projectView.indexOf('const syncPlaces =', start);
    const sentinel = '__all_project_places__';
    const changes = [];
    let closed = 0;
    const places = {
        options: [
            {value: sentinel, selected: true}, {value: '10'}, {value: '20'},
            {value: '30', disabled: true}, {value: ''},
        ],
        get 0() {return this;},
        val(ids) {
            if (ids === undefined) return this.options.filter(option => option.selected).map(option => option.value);
            this.options.forEach(option => option.selected = ids.includes(option.value));
            return this;
        },
        trigger(name) {changes.push(name); return this;},
        select2(action) {assert.equal(action, 'close'); closed++;},
        on(name, handler) {assert.equal(name, 'select2:select'); this.handler = handler;},
    };
    vm.runInNewContext(projectView.slice(start, end), {places, selectAllPlacesValue: sentinel});
    places.handler({params: {data: {id: sentinel}}});
    assert.deepEqual(places.val(), ['10', '20']);
    assert.deepEqual(changes, ['change']);
    assert.equal(closed, 1);
    places.val(['20']); // Removing a chip / unselecting an individual place.
    places.handler({params: {data: {id: '20'}}});
    assert.deepEqual(places.val(), ['20']);
    assert.deepEqual(changes, ['change']);
    assert.equal(closed, 1);
    // A subsequent action selects only the new geographically filtered options.
    places.options = [{value: sentinel}, {value: '40'}];
    places.handler({params: {data: {id: sentinel}}});
    assert.deepEqual(places.val(), ['40']);
    places.options = [];
    places.handler({params: {data: {id: sentinel}}});
    assert.deepEqual(places.val(), []);
});

function option(value, dataset = {}, selected = false) {
    return {value, dataset, selected, cloneNode() {return option(this.value, {...this.dataset}, this.selected);}};
}

function select(options) {
    return {
        options,
        get value() {return this.options.find(item => item.selected)?.value ?? this.options[0]?.value ?? '';},
        set value(value) {this.options.forEach(item => item.selected = item.value === value);},
        replaceChildren() {this.options = [];}, append(item) {this.options.push(item);},
    };
}

function reportHarness(editing = false) {
    const project = {value: '1'};
    const placeName = select([]), communityState = select([option(''), option('1')]);
    const hint = {textContent: ''};
    const allPlaceOptions = [option(''),
        option('Lugar A', {placeId: '10', stateId: '1', municipalityId: '11'}),
        option('Lugar B', {placeId: '20', stateId: '1', municipalityId: '11'}),
        option('Compartido', {placeId: '30', stateId: '1', municipalityId: '11'}),
        option('Guardado', {placeId: '40', stateId: '1', municipalityId: '11'}),
        option('Fuera del municipio', {placeId: '50', stateId: '1', municipalityId: '12'}),
    ];
    if (editing) allPlaceOptions.splice(1, 0, option('Guardado', {originalLocation: '1', stateId: '2', municipalityId: '21'}, true));
    const source = reportView.slice(reportView.indexOf('const locationBelongsToProject ='), reportView.indexOf('const setCommunityOptions ='))
        .replace("@json($editing ? (string) $report->proyecto_id : '')", JSON.stringify(editing ? '1' : ''))
        .replace('@json($editing)', String(editing))
        .replace('@json($editing ? $report->place_name : null)', JSON.stringify(editing ? 'Guardado' : null));
    const state = {cleared: 0, synced: 0};
    vm.runInNewContext(source + '\nexpose(syncAvailableLocations);', {
        project, placeName, allPlaceOptions, communityState, allCommunityStateOptions: communityState.options,
        communityMunicipality: {}, communityParish: {}, setCommunityOptions() {},
        projectLocations: {
            1: {states: [1], municipalities: [11], allStateMunicipalities: false, places: [10, 30, 50]},
            2: {states: [1], municipalities: [], allStateMunicipalities: true, places: [20, 30, 40]},
            3: {states: [1], municipalities: [], allStateMunicipalities: true, places: []},
        },
        select: id => {assert.equal(id, 'project-place-hint'); return hint;},
        clearCommunityLocation() {state.cleared++;}, syncPlaceLocation() {state.synced++;},
        expose(sync) {state.sync = sync;},
    });
    return {project, placeName, hint, state};
}

test('registration shows only the selected project places, including shared places, and clears previous selection', () => {
    const {project, placeName, hint, state} = reportHarness();
    state.sync();
    assert.deepEqual(placeName.options.map(item => item.value), ['', 'Lugar A', 'Compartido']);
    placeName.value = 'Lugar A';
    state.sync(true);
    assert.equal(placeName.value, 'Lugar A');
    project.value = '2';
    state.sync();
    assert.deepEqual(placeName.options.map(item => item.value), ['', 'Lugar B', 'Compartido', 'Guardado']);
    assert.equal(placeName.value, '');
    project.value = '3';
    state.sync();
    assert.deepEqual(placeName.options.map(item => item.value), ['']);
    assert.match(hint.textContent, /no tiene lugares/);
    project.value = '';
    state.sync();
    assert.equal(placeName.options.length, 1);
    assert.match(hint.textContent, /Seleccione un proyecto/);
});

test('saved location is retained only on its original project, without duplicate names or bypassing assignments', () => {
    const {project, placeName, state} = reportHarness(true);
    state.sync(true);
    placeName.value = 'Guardado';
    state.sync(true);
    assert.equal(placeName.value, 'Guardado');
    assert.equal(placeName.options.filter(item => item.value === 'Guardado').length, 1);
    assert.equal(placeName.options.find(item => item.value === 'Guardado').dataset.originalLocation, '1');
    project.value = '2';
    state.sync();
    assert.equal(placeName.value, '');
    assert.equal(placeName.options.filter(item => item.value === 'Guardado').length, 1);
    assert.equal(placeName.options.find(item => item.value === 'Guardado').dataset.placeId, '40');
    project.value = '3';
    state.sync();
    assert.deepEqual(placeName.options.map(item => item.value), ['']);
});
