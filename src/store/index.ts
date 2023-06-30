import { createStore } from 'redux';
import { combineReducers } from 'redux';
import { contentsReducer, hydrateContentsReducer } from './reducers/contents';

export type ApplicationState = {
  contents: ReturnType<typeof contentsReducer>;
}

const rootReducer = combineReducers({
  contents: contentsReducer
});

export const initStore = (initialState?: Partial<ApplicationState>) => ({
  contents: hydrateContentsReducer(initialState?.contents)
})

const store = createStore(rootReducer);

export default store;