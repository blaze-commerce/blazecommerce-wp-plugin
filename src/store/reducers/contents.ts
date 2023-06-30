import type { AnyAction } from 'redux';
import { SET_CONTENTS } from '../actions/types';

type Content = {
  blockId: string;
  blockType: string;
  position: number;
};

type ContentsReducer = Content[];

const initialState = [];

export const hydrateContentsReducer = (contents: ContentsReducer): ContentsReducer => {
  return contents;
};

export const contentsReducer = (state: ContentsReducer = initialState, action: AnyAction): ContentsReducer => {
  switch (action.type) {
    case SET_CONTENTS:
      const newState = action.payload;
      
      return newState;
    default:
      break;
  }
  return state;
}
