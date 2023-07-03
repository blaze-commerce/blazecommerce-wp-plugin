import { createSlice } from '@reduxjs/toolkit';
import type { Dictionary, PayloadAction } from '@reduxjs/toolkit'
import type { RootState } from '../index';

type Block = {
  id: number;
  order: number;
  isOpen: boolean;
  metadata: Dictionary<any>;
}

type BlockSlice = Map<number, Block>

const initialState: BlockSlice = new Map();

export const blockSlice = createSlice({
  name: 'counter',
  // `createSlice` will infer the state type from the `initialState` argument
  initialState,
  reducers: {
    setBlocks: (state, action: PayloadAction<Block[]>) => {
      const newState = new Map();

      action.payload.forEach(block => {
        newState.set(block.id, block);
      })

      state = newState
    }
  },
})

export const { setBlocks } = blockSlice.actions;

// Other code such as selectors can use the imported `RootState` type
export const selectCount = (state: RootState) => state.blocks;

export default blockSlice.reducer