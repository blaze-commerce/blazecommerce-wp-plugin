const { useState } = wp.element;
const {
    ColorPicker,
    Popover,
    Button,
} = wp.components;

export const ElementColorSelector = ({ value, setValue }) => {
    const [ isVisible, setIsVisible ] = useState( false );
    const [ selectedColor, setSelectedColor ] = useState( value );
    const [ popoverAnchor, setPopoverAnchor ] = useState();
    console.log('rerendering?')
    const showColorPicker = () => {
        console.log('clicked wow')
        setIsVisible(true);
    };

    const hideColorPicker = () => {
        setIsVisible(false);
    }

    const saveColor = () => {
        setValue(selectedColor);
        hideColorPicker();
    }

    const handleReset = () => {
        setSelectedColor('');
        setValue('');
        hideColorPicker();
    }

    console.log('selectedColor', selectedColor)
    
    return (
        <>
            <div
                ref={setPopoverAnchor}
                onClick={ showColorPicker }
                style={{
                    width: '20px',
                    height: '20px',
                    borderRadius: '9999px',
                    backgroundColor: value,
                    cursor: 'pointer',
                    border: '1px solid #e0e0e0'
                }}
            >
            </div>
            { isVisible && (
                <Popover
                    anchor={popoverAnchor}
                    placement="bottom-end"
                    position="top left"
                >
                    <ColorPicker
                        color={selectedColor}
                        onChange={setSelectedColor}
                        enableAlpha
                        defaultValue="#000"
                    />

                    <div style={{ display: 'flex', padding: '0 16px 20px', justifyContent: 'flex-end', gap: '10px' }}>
                        <Button isDestructive size="compact" onClick={handleReset}>Reset</Button>
                        <Button variant="secondary" size="compact" onClick={hideColorPicker}>Cancel</Button>
                        <Button variant="primary" size="compact" onClick={saveColor}>Save</Button>
                    </div>
                </Popover>
            ) }
        </>
    )
}