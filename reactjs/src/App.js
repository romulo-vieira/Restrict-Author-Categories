import React, { useState, useEffect, useRef} from 'react';
import './admin-page.css';
import UserSelector from './UserSelector';
import OnOffButton from './OnOffButton';
import { getUsersData, setUserDB } from './resources';
import TermContainer from './TermContainer';

function App() {

  const userUlRef = useRef(null); // stores Ul element that show all the users
  const userUlTimer = useRef(null); // stores setInterval data
  const noUsersFound = useRef(false); // false if get users query returns no value
  const usersData = useRef({ // query data
    quant: 200, // load 200 users per query
    offset: 0, // offset for next query
    totalUsers: 0, // total of terms that can be queried
    search: '' // search user's query
  });

  const [loadingState, setLoadingState] = useState(false); // loading users state
  const [reRender, setReRender] = useState(false); // use to force re-render
  const [currentUser, setCurrentUser] = useState({}); // selected user data
  const [allUsers, setAllUsers] = useState([]);

  /**
   * Set Users data from returned DB data
   * @param {object} newUsersData 
   */
  const newUsersDataHandler = (newUsersData) => {

    // the query has returned any user
    if(newUsersData.data.length > 0){

      usersData.current.totalUsers = newUsersData.totalUsers;
      setAllUsers([
        ...allUsers,
        ...newUsersData.data.map(user => ({
          userId: parseInt(user.userId),
          userName: user.userName,
          isActive: user.isActive === '1' ? true : false
        }))
      ]);

    // the query returned no user
    }else{
      noUsersFound.current = true;
      setReRender(!reRender);
    }
  }

  /**
   * When new users are showed
   */
  useEffect(()=>{

    if(allUsers.length > 0){
      usersData.current.offset = allUsers.length;
      setLoadingState(false); // setting loadingState to false will start a new Users request to the DB
    }

  },[allUsers])

  /**
   * When loadingState changes
   * if loadingState === false, start a new User request to the DB 
   * if loadingState === true, do nothing
   */
  useEffect(()=>{

    if(!loadingState){

      if(userUlTimer.current === null){

        userUlTimer.current = setInterval(() => {

            const scrollPos = (userUlRef.current.clientHeight + userUlRef.current.scrollTop) / userUlRef.current.scrollHeight;

            if(scrollPos > 0.8){ // if scroll position of userUlRef is at 80% of the height, query DB for new Users data.

              clearInterval(userUlTimer.current);
              userUlTimer.current = null;
              setLoadingState(true);
              getUsersData(usersData.current.quant, usersData.current.offset, newUsersDataHandler);

            }

        }, 100); // after 100 miliseconds check for userUlRef's scroll position.
        
      }
    }

  },[loadingState]);

  /**
   * Changing data of the Current User
   * @param {id} newCurrentUser 
   */
  const changeCurrentUser = (newCurrentUser) => {
    if(typeof currentUser.userId === 'undefined'){ // selecting a user when none was selected
      
      setCurrentUser(newCurrentUser);

    }else{

      if(newCurrentUser.userId !== currentUser.userId){ // changing current user
        setCurrentUser(newCurrentUser);

      }else if(newCurrentUser.isActive !== currentUser.isActive){ // changing isActive data of the current user at the DB

        // updating users list with new currentUser state
        const newUsers = allUsers.slice();
        if(
          typeof newCurrentUser.arrKey !== "undefined" &&
          newUsers[newCurrentUser.arrKey].userId === newCurrentUser.userId
        ){

          newUsers[newCurrentUser.arrKey].isActive = newCurrentUser.isActive;
          setAllUsers(newUsers);

        }else{

          setAllUsers(
            allUsers.map(user => {
              if(user.userId === newCurrentUser.userId){
                return {...user, isActive: newCurrentUser.isActive};
              }else{
                return user;
              }
            })
          );

        }

        // updating isActive user state at DB
        setUserDB(
          newCurrentUser.userId,
          newCurrentUser.isActive
        );

        setCurrentUser(newCurrentUser);
      }
    }
  }

  return (
    <>
      <div id="resautcat-content-user" className="resautcat-type-container">
        <div id="resautcat-user-title-container" className={"resautcat-title-container" + (currentUser.userId ? ' selected-user' : '')}>
            <h3 id="resautcat-user-title" className="resautcat-type-title">
              {currentUser.userName || 'Users'}
            </h3>
            {!!currentUser.userId && (
              <OnOffButton
                title="Activate/Deactivate User"
                selected={currentUser.isActive ? true : false}
                onClick={e => changeCurrentUser({
                  ...currentUser,
                  isActive: !currentUser.isActive
                })}
              />
            )}
        </div>
        <ul id="resautcat-user-ul" className="resautcat-type-ul" ref={userUlRef}>
          {allUsers.map((user, arrKey) => (
            <UserSelector
              key={user.userId}
              changeCurrentUser={changeCurrentUser}
              arrKey={arrKey}
              userData = {user}
              isSelected={currentUser.userId === user.userId ? true : false}
            />
          ))}
          {(
            !noUsersFound.current &&
            allUsers.length > 0 &&
            allUsers.length < usersData.current.totalUsers
          ) && (
            <li className="resautcat-user-li resautcat-type-li resautcat-loading-li">
              <button>Loading Users</button>
              <div className="lds-dual-ring"></div>
            </li>
          )}
          {(
            !noUsersFound.current &&
            allUsers.length === 0
          ) && (
            <div className="resautcat-message">
              <div className="lds-dual-ring"></div>
            </div>
          )}
          {(
            noUsersFound.current &&
            allUsers.length === 0
          ) && (
            <div className="resautcat-message">
              <h3>No User Found</h3>
              <p>You can only select non-admin users</p>
            </div>
          )}
        </ul>
      </div>

      <div id="resautcat-content-term" className="resautcat-type-container">
        <TermContainer
          changeCurrentUser={changeCurrentUser}
          currentUser={currentUser}
          currentUserId={currentUser.userId}
          parent={0}
          isPrimary={true}
          chainChangeCanEditPost = {[]}
          prevTermCanEditPost = {null}
          parentSetHideChildren = {null}
          usersLength = {allUsers.length}
        />
      </div>
    </>
  );
}

export default App;