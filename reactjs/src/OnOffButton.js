import React  from 'react';

/**
 * A simple On/Off button Component.
 * When props.selected === true, is active.
 * Runs onClick event like the any Button component
 * @param {object} props 
 */
function OnOffButton(props) {
  return (
      <button
        className={"resautcat-on-off-button" + (props.selected ? ' selected' : '')}
        onClick={e => props.onClick(e)}
        title={props.title || false}
      >
        <div className="resautcat-on-off-button-back"></div>
        <div className="resautcat-on-off-button-round"></div>
      </button>
  );
}

export default OnOffButton;