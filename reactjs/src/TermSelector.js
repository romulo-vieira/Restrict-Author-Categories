import React, { useEffect, useState, useRef } from 'react';
import { minusSvg, plusSvg } from './svgIcons';
import TermContainer from './TermContainer';
import { setTermDB } from './resources';

function TermSelector(props) {

  const [termCanEditPost, setTermCanEditPost] = useState(props.termData.termCanEditPost); // if TermSelector button is selected
  const [loadChildren, setLoadChildren] = useState(false); // if true, start loading TermContainer component of children categories
  const [hideChildren, setHideChildren] = useState(true); // show/hide loaded children TermContainer component
  const [childrenDataIsLoaded, setChildrenDataIsLoaded] = useState(false); // has loaded children categories data
  
  const unhideParent = useRef(false); // if true, parent will auto show children TermContainer, showing this TermSelector component, when loaded
                                      // it's useful to only auto show children categories if any of children categories are selected
  
  // canEditPost is when the button is selected or not
  const changeCanEditPost = (newTermCanEditPost) => {
    if(newTermCanEditPost !== termCanEditPost){
      setTermCanEditPost(newTermCanEditPost);

      // changing term state on the DB
      setTermDB(
        props.termData.termId,
        props.currentUser.userId,
        newTermCanEditPost
      );
    }

    // only change term state on frontend if it's different from the current one
    if(newTermCanEditPost){
      props.changeCurrentUser({
        ...props.currentUser,
        isActive: newTermCanEditPost
      })
    }
  }

  /**
   * On click at the component, change if user can edit or not the post
   */
  const buttonClickHandler = () => {
    const newTermCanEditPost = !termCanEditPost;

    if(newTermCanEditPost){
      props.chainChangeCanEditPost.forEach( f => f(newTermCanEditPost) );
    }

    changeCanEditPost(newTermCanEditPost);
  };

  /**
   * When you change canEditPost of child term to true, the parent also needs to be setted to true
   * Because the block editor of WP only shows child categories if parent categories are selected
   */
  useEffect(()=>{
    if(props.prevTermCanEditPost === false){
      changeCanEditPost(false);
    }
  },[props.prevTermCanEditPost])

  // auto load children when component is mounted
  useEffect(()=>{
    
    if(
      props.termData.termChildren > 0 &&
      props.termData.termCanEditPost
    ){
      setLoadChildren(true);
      unhideParent.current = true;
    }

    // parent will show children (this component) if term is selected
    if(props.termData.termCanEditPost){
      if(props.parentSetHideChildren !== null){
        props.parentSetHideChildren();
      }
    }

  },[]);

  // show children categories (load children before, if not loaded)
  const expandChildren = () => {
    if(loadChildren === false){
      setLoadChildren(true);
      setHideChildren(false);
    }else{
      setHideChildren(!hideChildren);
    }
  }

  return (
    <li className={"resautcat-term-li resautcat-type-li" + (termCanEditPost ? ' active' : '') + ((childrenDataIsLoaded && !hideChildren) ? ' expanded' : '')}>
      <div className="resautcat-term-li-container">
        <button
          className="resautcat-term-name resautcat-type-button-container"
          onClick={e => buttonClickHandler(e)}
        >
          <div className={"resautcat-term-button resautcat-type-button" + (termCanEditPost ? ' selected' : '')}></div>
          {props.termData.termName}
        </button>
        {(props.termData.termChildren > 0) && (
          <>
            <button
              className="resautcat-term-expand-button"
              onClick={e => expandChildren(e)}
              title="Expand Sub-Categories"
              disabled={(loadChildren && !childrenDataIsLoaded) ? 'disabled' : false}
            >
              <div className={"resautcat-term-expand-svg-container"}>
                { (loadChildren && !childrenDataIsLoaded)
                  ?
                    <div className="lds-dual-ring"></div>
                  :
                    (childrenDataIsLoaded && !hideChildren)
                    ?
                      minusSvg
                    :
                      plusSvg
                }
              </div>
            </button>
          </>
        )}
      </div>
      {loadChildren && (
        <TermContainer
          changeCurrentUser = {props.changeCurrentUser}
          currentUser = {props.currentUser}
          currentUserId={props.currentUserId}
          parent = {props.termData.termId}
          setChildrenDataIsLoaded = {setChildrenDataIsLoaded}
          chainChangeCanEditPost = {[...props.chainChangeCanEditPost, changeCanEditPost]}
          prevTermCanEditPost = {termCanEditPost}
          hideContainer = {hideChildren}
          parentSetHideChildren = {unhideParent.current ? setHideChildren : null}
        />
      )}
    </li>
  );
}

export default TermSelector;