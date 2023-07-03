import React from 'react';
// import { useAppSelector, useAppDispatch } from './hooks'
// import { decrement, increment, selectCount } from './store/reducers/counterSlice'
import { Panel } from './components/Panel'
import { Canvas } from './components/Canvas'

export const App = () => {
  // const count = useAppSelector(selectCount)
  // const dispatch = useAppDispatch();
  // const onClick = () => {
  //   dispatch(increment())
  // }

  return (
    <div>
      <div>
        test
        <Panel />
        <Canvas />
      </div>
    </div>
  )
}
