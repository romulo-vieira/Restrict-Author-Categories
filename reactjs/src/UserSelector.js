import React from 'react';

function UserSelector(props) {

  const buttonClickHandler = () => {
    props.changeCurrentUser({...props.userData, arrKey: props.arrKey});
  };

  return (
    <li
      className={"resautcat-user-li resautcat-type-li"  + (props.userData.isActive ? ' active' : '') + (props.isSelected ? ' selected' : '')}
    >
      <button
        className="resautcat-user-name resautcat-type-button-container"
        onClick={e => buttonClickHandler(e)}
      >
        {props.userData.userName}
      </button>
    </li>
  );
}

export default UserSelector;