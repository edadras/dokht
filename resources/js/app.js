import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

import fabricProfile from './components/fabric-profile';
import measurementForm from './components/measurement-form';
import orderBoard from './components/order-board';
import patternEditor from './components/pattern-editor';
import serverModelViewer from './components/server-model-viewer';
import sketchPad from './components/sketch-pad';

Alpine.plugin(collapse);

Alpine.data('fabricProfile', fabricProfile);
Alpine.data('measurementForm', measurementForm);
Alpine.data('orderBoard', orderBoard);
Alpine.data('patternEditor', patternEditor);
Alpine.data('serverModelViewer', serverModelViewer);
Alpine.data('sketchPad', sketchPad);

window.Alpine = Alpine;

Alpine.start();
