import attributes from './attributes.json';
import cloneDeep from 'lodash/cloneDeep';

const newAttributes = cloneDeep( attributes );
delete attributes.cloudName;

const Deprecated = () => [ { attributes } ];

export default Deprecated;
