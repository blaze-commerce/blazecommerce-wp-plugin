const { useState } = wp.element;
const {
    ColorPicker,
    Popover,
} = wp.components;

export const ElementColorSelector = ({ value, setValue }) => {
    const [ isVisible, setIsVisible ] = useState( false );
    const toggleVisible = () => {
        setIsVisible( ( state ) => ! state );
    };
    return (
        <div onClick={ toggleVisible } style={{
            width: '20px',
            height: '20px',
            borderRadius: '9999px',
            backgroundColor: value,
            cursor: 'pointer',
            border: '1px solid #e0e0e0'
        }}>
            { isVisible && (
                <Popover
                    placement="bottom-end"
                    position="top left"
                >
                    <ColorPicker
                        color={value}
                        onChange={setValue}
                        enableAlpha
                        defaultValue="#000"
                    />
                </Popover>
            ) }
        </div>
    )
}