<div class="modal fade" id="fastScan" tabindex="-1" aria-labelledby="fastScanLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fastScanLabel">{{ __('Rebuild the cluster based on previously received data') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="clusteringLevelFast">{{ __('clustering level') }}</label>
                    {!! Form::select('clusteringLevel', [
                        'light' => 'light',
                        'soft' => 'soft',
                        'pre-hard' => 'pre-hard',
                        'hard' => 'hard',
                    ], $request['clusteringLevel'] ?? null, ['class' => 'form-select', 'id' => 'clusteringLevelFast']) !!}
                </div>
                <div class="mb-3">
                    <label class="form-label" for="engineVersionFast">{{ __('Merging Clusters') }}</label>
                    {!! Form::select('engineVersion', [
                        'max_phrases' => 'Фразовый перебор и поиск максимального (13.01)',
                        '1501' => 'Фразовый перебор и поиск максимального (15.01)',
                    ], $request['engineVersion'] ?? null, ['class' => 'form-select', 'id' => 'engineVersionFast']) !!}
                </div>
                <div class="mb-3">
                    <label class="form-label" for="ignoredDomains">{{ __('Ignored domains') }}</label>
                    <textarea class="form-control" id="ignoredDomains" rows="4">{{ $request['ignoredDomains'] ?? '' }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="ignoredWords">{{ __('Ignored words') }}</label>
                    <textarea class="form-control" id="ignoredWords" rows="4">{{ $request['ignoredWords'] ?? '' }}</textarea>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="brutForce">
                    <label class="form-check-label" for="brutForce">{{ __('Additional bulkhead') }}</label>
                </div>
                <div id="brutForceCountBlock" class="d-none">
                    <div class="mb-3">
                        <label class="form-label" for="gainFactor">{{ __('Gain factor(%)') }}</label>
                        <input class="form-control" type="number" id="gainFactor" value="{{ $request['gainFactor'] ?? 10 }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="brutForceCount">{{ __('Minimum cluster size for re-bulkhead') }}</label>
                        <input type="number" class="form-control" id="brutForceCount" value="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="reductionRatio">{{ __('Minimum multiplier') }}</label>
                        <select id="reductionRatio" class="form-select">
                            <option value="pre-hard">pre-hard</option>
                            <option value="soft">soft</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                <button type="button" class="btn btn-primary" id="brutForceFast" data-bs-dismiss="modal">{{ __('Rebuild') }}</button>
            </div>
        </div>
    </div>
</div>
