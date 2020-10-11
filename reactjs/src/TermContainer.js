import React, { useState, useEffect, useRef } from 'react';
import { getTermsData, getTermsDataPrimary } from './resources';
import TermSelector from './TermSelector';
import { plusSvg } from './svgIcons';

function TermContainer(props) {

  const hasLoadedUser = useRef(props.isPrimary ? false : true);
  const termUlRef = useRef(null); // stores Ul element that show all the categories (or subcategories)
  const termUlTimer = useRef(null); // stores setInterval data
  const noTermsFound = useRef(false); // false if the get terms query returns no value
  const firstRun = useRef(true); // verifies if componentDidMount on subcategories

  const allGetTerms = useRef([]); // register all "get terms from DB" functions
  const allGetTermsTracker = useRef(0); // keeping track of the last "get terms from DB" functions fired

  const hasChangedUser = useRef(null); // when Selected User changes
  const termsData = useRef({ // query data
    quant: props.isPrimary ? 200 : 10, // load 200 terms per query at primary categories. 20 at subcategories
    userId: 0, // current user id
    parent: props.parent, // parent category id
    offset: 0, // offset for next query
    search: '', // search terms's query
    totalTerms: 0 // total of terms that can be queried
  });
  
  const [loadingState, setLoadingState] = useState(props.isPrimary ? true : false); // loading users state
  const [reRender, setReRender] = useState(false); // use to force re-render
  const [reRenderLoading, setReRenderLoading] = useState(false); // re-run loadingState's useEffect but keepgins loadingState value to true.
  const [allTerms, setAllTerms] = useState([]);

  /**
   * Set Terms data from returned DB data
   * @param {object} newTermsData 
   */
  const newTermsDataHandler = (newTermsData) => {

      // the query has returned any term
      if(newTermsData.data.length > 0){

        termsData.current.totalTerms = parseInt(newTermsData.totalTerms);
        setAllTerms(allTerms.slice(0,parseInt(newTermsData.offset)).concat(
          newTermsData.data.map(term => (
            {
              termId: parseInt(term.termId),
              termName: term.termName,
              termParent: parseInt(term.termParent),
              termCanEditPost: term.termCanEditPost === '1' ? true : false,
              termCanSeePost: term.termCanSeePost === '1' ? true : false,
              termChildren: parseInt(term.termChildren)
            }
          ))
        ));

      // the query returned no term
      }else{
        noTermsFound.current = true;
        setReRender(!reRender);
        setLoadingState(true);
      }
  }

  /**
   * Request data on DB
   */
  const getTermsDataHandler = () => {

    const funcIndex = allGetTerms.current.length;
    var f = newTermsDataHandler;
    allGetTerms.current = [...allGetTerms.current, f];

    // query params
    const getTermsParams = {
      userId: termsData.current.userId,
      quant: termsData.current.quant,
      offset: termsData.current.offset,
      parent: termsData.current.parent,
      search: termsData.current.search,
      callback: (response) => {
        if(
          typeof allGetTerms.current[funcIndex] !== 'undefined' &&
          allGetTerms.current[funcIndex] !== null
        ){
          allGetTerms.current[funcIndex](response);
        }
      }
    }

    if(props.isPrimary){ // for subcategories
      getTermsDataPrimary( getTermsParams );
    }else{ // for primary categories
      getTermsData( getTermsParams );
    }
  }

  /**
   * When new terms are showed
   */
  useEffect(()=>{
    
    // subcategories
    if(!props.isPrimary){
      if(typeof allTerms[0] !== 'undefined' && typeof props.setChildrenDataIsLoaded !== 'undefined'){
        props.setChildrenDataIsLoaded(true);
      }

      if(firstRun.current){ // componentDidMount
        
        termsData.current.userId = props.currentUserId;
        firstRun.current = false;
        setLoadingState(true); // currently loading terms
        getTermsDataHandler(); // load terms from DB
      }
    }

    // primary categories (the ones with no parent)
    if(allTerms.length > 0){
      termsData.current.offset = allTerms.length;
      setLoadingState(false); // load new terms
    }

  },[allTerms]);

  /**
   * When loadingState changes
   * if loadingState === false, start a new User request to the DB 
   * if loadingState === true, do nothing
   */
  useEffect(()=>{

    if(props.isPrimary){

      if(!loadingState){

        if(termUlTimer.current === null){
  
          termUlTimer.current = setInterval(()=> {
            
            const scrollPos = (termUlRef.current.clientHeight + termUlRef.current.scrollTop) / termUlRef.current.scrollHeight;
  
            if(scrollPos > 0.8){ // if scroll position of termUlRef is at 80% of the height, query DB for new Users data.
              
              clearInterval(termUlTimer.current);
              termUlTimer.current = null;
              setLoadingState(true);
              getTermsDataHandler();

            }
          }, 100); // after 100 miliseconds check for termUlRef's scroll position.
        }
      }
    }

  },[loadingState,reRenderLoading])

  /**
   * Reset data when new user is set
   */
  useEffect(()=>{

    if(hasChangedUser.current === null){ // componentDidMount
      hasChangedUser.current = false;
    }else{

      termsData.current.userId = props.currentUserId;

      if(!props.isPrimary){ // if is subcategory. just clear all the "get terms from DB" functions
        
        allGetTerms.current = [];

      }else{ // if is primary category, first you need to know if any user is selected.

        if(hasLoadedUser.current){

          /**
           * Reseting data
           */
          
          // clearing "get terms from DB" functions
          const trackerIndex = (allGetTermsTracker.current - 1) <= 0 ? 0 : allGetTermsTracker.current - 1;
          for (let index = trackerIndex; index < allGetTerms.current.length; index++) {
            allGetTerms.current[index] = null;
          }
          allGetTermsTracker.current = allGetTerms.current.length - 1;

          // reseting current running timer
          if(termUlTimer.current !== null){
            clearInterval(termUlTimer.current);
          }
          termUlTimer.current = null;

          // resetting other Refs
          noTermsFound.current = false;
          termsData.current.totalTerms = 0;
          termsData.current.offset = 0;

          setAllTerms([]);
          if(loadingState){
            setLoadingState(false);
          }else{
            setReRenderLoading(!reRenderLoading); // re-run loadingState's useEffect but keepgins loadingState value to true.
          }
  
        }else{ // first user is selected
          hasLoadedUser.current = true;
          setLoadingState(false); // calling loadingState's useEffect
        }
      }
    }

  },[props.currentUserId]);

  return (
    <ul id={props.isPrimary ? "resautcat-term-ul" : ''} ref={termUlRef} className={"resautcat-type-ul" + (props.hideContainer ? ' hide-ul' : '')}>

      {allTerms.map( term => (
        <TermSelector
          key = {term.termId}
          currentUser = {props.currentUser}
          changeCurrentUser = {props.changeCurrentUser}
          termData = {term}
          chainChangeCanEditPost = {props.chainChangeCanEditPost}
          prevTermCanEditPost = {props.prevTermCanEditPost}
          parentSetHideChildren = {props.parentSetHideChildren}
          currentUserId={props.currentUserId}
        />
      ))}

      {(
        props.isPrimary &&
        allTerms.length > 0 &&
        allTerms.length < termsData.current.totalTerms &&
        hasLoadedUser.current
      ) && (
        <li className="resautcat-term-li resautcat-type-li resautcat-loading-li">
          <button>Loading Categories</button>
          <div className="lds-dual-ring"></div>
        </li>
      )}

      {(
        props.isPrimary &&
        allTerms.length === 0 &&
        noTermsFound.current &&
        hasLoadedUser.current
      ) && (
        <div className="resautcat-message">
          <h3>No Category Found</h3>
        </div>
      )}

      {(
        props.isPrimary &&
        allTerms.length === 0 &&
        hasLoadedUser.current &&
        !noTermsFound.current
      ) && (
        <div className="resautcat-message">
          <div className="lds-dual-ring"></div>
        </div>
      )}

      {(
        props.isPrimary &&
        props.usersLength > 0 &&
        !hasLoadedUser.current
      ) && (
        <div className="resautcat-message">
          <h3>Select a User</h3>
        </div>
      )}

      {(
        !props.isPrimary &&
        allTerms.length < termsData.current.totalTerms &&
        hasLoadedUser.current
      ) && (
        <li className="resautcat-term-li resautcat-type-li resautcat-load-more-terms-li">
          {loadingState
          ?
            <div className="lds-dual-ring"></div>
          :
            <button
              onClick={e => {
                setLoadingState(true);
                getTermsDataHandler();
              }}
            >
              {plusSvg}
              <span>Load More</span>
            </button>
          }
        </li>
      )}

    </ul>
  );
}

export default TermContainer;