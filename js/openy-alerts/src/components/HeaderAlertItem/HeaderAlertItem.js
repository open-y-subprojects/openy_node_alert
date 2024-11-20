import React, { Component } from 'react';
import cookie from 'react-cookies';
import parse from 'html-react-parser';
import { closeAlert } from '../../actions/helpers';
import connect from 'react-redux/es/connect/connect';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import { faTimes, faExclamationCircle, faCircle } from '@fortawesome/free-solid-svg-icons'

/**
 * Renders alert item.
 */
class AlertItem extends Component {
  constructor(props) {
    super(props);
    this.state = {
      isMobile: false,
      largeDescription: false,
      expanded: false,
    };
  }

  componentDidMount(){
    const stripHTML = (html) => {
      let doc = new DOMParser().parseFromString(html, 'text/html');
      return doc.body.textContent || "";
    };

    const textContent = stripHTML(this.props.description);
    const length = textContent.length;

    this.setState({
      largeDescription: length > 150
    });
    this.showInMobile();
    window.addEventListener('resize', this.showInMobile.bind(this));
  };

  showInMobile() {
    const mediaQuery = window.matchMedia('(max-width: 768px)');
    // Used for updating indents for main region.
    if(this.state.isMobile !== mediaQuery.matches) {
      this.setState({
        isMobile: mediaQuery.matches,
      });
    }
  }

  toggleExpand = (e) => {
    this.setState({ expanded: !this.state.expanded});
    window.dispatchEvent( new Event('resize'));
    setTimeout(() => { this.focusItem(); }, 100);
  };

  focusItem = () => {
    this.props.focus(parseInt(this.props.index));
    let slicks = document.getElementsByClassName("slick-list");
    for (let slick of slicks) {
      slick.scrollLeft = 0;
      // Additionally reset horizontal scroll for screen readers.
      setTimeout(() => { slick.scrollLeft = 0; }, 100);
    }
  };
  render() {
    const { isMobile, expanded, largeDescription } = this.state;
    const expandClass = !expanded && isMobile && largeDescription ? ' alert-short hidden' : ' alert-full';

    let closeItem = () => {
      let ad = cookie.load('alerts_dismiss');
      if (typeof ad !== 'undefined') {
        ad.push(parseInt(this.props.alertId));
      } else {
        ad = [parseInt(this.props.alertId)];
      }
      cookie.save('alerts_dismiss', ad, {path: '/'});
      this.props.closeAlert(parseInt(this.props.alertId));
      if (this.props.index === this.props.slider.props.children[0].length - 1) {
        setTimeout(() => (this.props.slider.slickPrev()), this.props.slider.props.speed);
      }
    };

    let alertStyle = '';
    let iconStyle = {
      backgroundColor: this.props.iconColor ? `#${this.props.iconColor}` : 'blue'
    };
    let linkStyle = {
      color: this.props.txtColor ? `#${this.props.txtColor}` : 'white',
      borderColor: this.props.txtColor ? `#${this.props.txtColor}` : 'white'
    };

    let alertStyleClass = this.props.alertStyle ? this.props.alertStyle : 'classic';
    let isClassic = alertStyleClass === 'classic';
    if (isClassic) {
      alertStyle = {
        backgroundColor: this.props.bgColor ? `#${this.props.bgColor}` : 'blue',
        color: this.props.txtColor ? `#${this.props.txtColor}` : 'white'
      };
      iconStyle = {
        color: this.props.iconColor ? `#${this.props.iconColor}` : 'blue'
      };
    }

    let alertClassName = 'alert' + this.props.alertId + ' alert-item ' + alertStyleClass;

    let alertContentClasses = this.props.linkTitle ?
      "col-xs-12 col-sm-6 col-md-6 col-lg-6" :
      "col-xs-12 col-sm-11 col-md-11 col-lg-11";
    return (
      <div className="site-alert site-alert--header">
        <div
          role="article"
          data-nid={this.props.alertId}
          style= {alertStyle}
          className={alertClassName}
          tabindex="0"
          data-idx={this.props.index}
          onFocus={() => this.focusItem()}
        >
          <div className="container header-alert">
            <div className="row site-alert__wrapper">
              <div className={alertContentClasses}>
                <div className="site-alert__title">
                  {this.props.iconColor && (
                    <div className="site-alert__icon">
                      {isClassic &&
                        <span className="fa-layers fa-fw" style={iconStyle}>
                      <FontAwesomeIcon icon={faCircle} color="white"/>
                      <FontAwesomeIcon icon={faExclamationCircle}/>
                    </span>
                      }
                      {!isClassic &&
                        <span class="alert-icon"></span>
                      }
                    </div>
                  )}
                  {parse(this.props.label)}
                </div>
                <div
                  className={'site-alert__content header-alert__content' + expandClass}>
                  {parse(this.props.description)}
                </div>
              </div>
              {this.props.linkTitle && (
                <div
                  className={'col-xs-12 col-sm-5 col-md-5 col-lg-5 site-alert__cta' + expandClass}>
                  <div className="field-alert-link">
                    <a href={this.props.linkUrl} style={linkStyle}>
                      {this.props.linkTitle}
                    </a>
                  </div>
                </div>
              )}
              {isMobile && largeDescription &&
                <div className="site-alert__content expand__wrapp justify-content-center p-2">
                  <button
                    className={"btn expand__button " + (expanded ? 'expanded' : '')}
                    onClick={this.toggleExpand.bind(this)}
                    title={expanded ? "Show less" : "Show more"}
                    style={{
                      color: this.props.txtColor ? `#${this.props.txtColor}` : 'white',
                    }}
                  >
                  </button>
                </div>
              }
              <span
                className="site-alert__dismiss"
                onClick={() => closeItem()}
                aria-label="Close alert"
                onFocus={() => this.focusItem()}
              >
                <span className="visually-hidden">
                  Close alert {parse(this.props.label)}
                </span>
                <FontAwesomeIcon icon={faTimes} />
              </span>
            </div>
          </div>
        </div>
      </div>
    );
  }
}
const mapDispatchToProps = dispatch => {
  return {
    closeAlert: id => {
      dispatch(closeAlert(id));
    }
  };
};

const mapStateToProps = state => {
  return {
    alerts: state.init.alerts
  };
};

const HeaderAlertItem = connect(
  mapStateToProps,
  mapDispatchToProps
)(AlertItem);

export default HeaderAlertItem;
