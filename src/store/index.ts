import { configureStore } from '@reduxjs/toolkit';
// import { contentsReducer } from './reducers/contents';
import counterReducer from './reducers/counterSlice';
import blockReducer from './reducers/blockSlice';

export const store = configureStore({
  reducer: {
    counter: counterReducer,
    blocks: blockReducer,
  }
});

export type RootState = ReturnType<typeof store.getState>

export type AppDispatch = typeof store.dispatch;