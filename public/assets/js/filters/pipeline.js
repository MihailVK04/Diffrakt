import { FILTER_MAP } from "./canvas.js";

export function runPipeline(source, steps) {
    const canvas = _sourceToCanvas(source);
    const ctx = canvas.getContext('2d');

    if (!steps || steps.length === 0) {
        return canvas.toDataURL('image/jpg', 0.92);
    }

    let imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

    for (const step of steps) {
         if (step.sub_pipeline_id !== null && step.sub_pipeline_id !== undefined) {
            console.warn(
                `[pipeline.js] Skipping step with sub_pipeline_id=${step.sub_pipeline_id} — ` +
                'sub-pipelines are not supported in the client-side runner.'
            );
            continue;
        }
 
        const fn = FILTER_MAP[step.filter_id];
 
        if (!fn) {
            console.warn(`[pipeline.js] Unknown filter_id=${step.filter_id} — skipping.`);
            continue;
        }
 
        imageData = fn(imageData, step.params ?? {});
    }
    ctx.putImageData(imageData, 0, 0);
 
    return canvas.toDataURL('image/jpeg', 0.92);
}

export function runPipelineToCanvas(source, steps) {
    const canvas = _sourceToCanvas(source);
    const ctx = canvas.getContext('2d');
 
    if (!steps || steps.length === 0) {
        return canvas;
    }
 
    let imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
 
    for (const step of steps) {
        if (step.sub_pipeline_id !== null && step.sub_pipeline_id !== undefined) {
            console.warn(
                `[pipeline.js] Skipping step with sub_pipeline_id=${step.sub_pipeline_id} — ` +
                'sub-pipelines are not supported in the client-side runner.'
            );
            continue;
        }
 
        const fn = FILTER_MAP[step.filter_id];
 
        if (!fn) {
            console.warn(`[pipeline.js] Unknown filter_id=${step.filter_id} — skipping.`);
            continue;
        }
 
        imageData = fn(imageData, step.params ?? {});
    }
 
    ctx.putImageData(imageData, 0, 0);
 
    return canvas;
}

function _sourceToCanvas(source) {
    const canvas = document.createElement('canvas');
 
    if (source instanceof HTMLCanvasElement) {
        canvas.width = source.width;
        canvas.height = source.height;
        canvas.getContext('2d').drawImage(source, 0, 0);
    } else {
        canvas.width = source.naturalWidth || source.width;
        canvas.height = source.naturalHeight || source.height;
        canvas.getContext('2d').drawImage(source, 0, 0);
    }
 
    return canvas;
}